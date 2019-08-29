<?php

namespace Dwo\ProcessProcessor;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

class QueuedProcessor
{
    /** @var int */
    private $parallel;

    /** @var OutputInterface|null */
    private $output;

    /** @var Process[] */
    private $processes = [];

    /** @var Process[] */
    private $processesAll = [];

    /** @var callable|null */
    private $onTerminated;

    /** @var Stopwatch|null */
    private $watch;

    /** @var int|null */
    private $lastTick = null;

    /**
     * QueuedProcessor constructor.
     *
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output = null, int $parallel = 10)
    {
        $this->parallel = $parallel;
        $this->output = $output ?? new NullOutput();
    }

    /**
     * @param callable|null $onTerminated
     */
    public function setOnTerminated(?callable $onTerminated): void
    {
        $this->onTerminated = $onTerminated;
    }

    public function hasProcesses()
    {
        return count($this->processes);
    }

    public function addProcess($key, Process $process)
    {
        $this->processes[$key] = $process;
        $this->processesAll[$key] = $process;
    }

    public function addProcessAndStart($key, Process $process)
    {
        $this->addProcess($key, $process);

        if ($this->startable($this->processes, $this->parallel)) {
            $this->startProcesses([$key => $process]);
        }
    }

    public function addProcesses($processes)
    {
        foreach ($processes as $key => $process) {
            $this->addProcess($key, $process);
        }
    }

    public function setParallel(int $parallel)
    {
        $this->parallel = $parallel;
    }

    /**
     * @param iterable|callable|Process|Process[] $processes
     * @param int                                 $parallel
     * @param callable|null                       $onTerminated
     */
    public function wait($processes = [], int $parallel = null, callable $onTerminated = null): void
    {
        if(null !== $this->parallel) {
            $this->parallel = $parallel;
        }
        if(null !== $this->onTerminated) {
            $this->onTerminated = $onTerminated;
        }

        $this->addProcesses($this->prepareProcesses($processes));

        do {
            $startable = $this->findStartableProcesses();
            if (!empty($startable)) {
                $this->startProcesses($startable);
            }

            $this->tick();

            if ($countLeft = count($this->processes)) {
                sleep(1);
            }
        } while ($countLeft);
    }

    public function tick(): void
    {
        //only one tick per sec
        if (null !== $this->lastTick) {
            if (time() === $this->lastTick) {
                return;
            }
        }

        //check terminated processes, unset process and call onTerminated
        foreach ($this->processes as $key => $process) {
            if ($process->isTerminated()) {
                unset($this->processes[$key]);

                if (null !== $this->onTerminated) {
                    $onTerminated = $this->onTerminated;
                    $onTerminated($key, $process);
                }
            }
        }

        $countAll = count($this->processesAll);
        $countLeft = count($this->processes);

        $this->output->writeln(
            sprintf(
                '%s ... %s sek | %s/%s done',
                $countLeft ? 'WAITING' : ' =DONE=',
                date('i:s', $this->getWatch()->getDuration() / 1000),
                $countAll - $countLeft,
                $countAll
            )
        );

        $this->lastTick = time();
    }

    protected function startProcesses(array $processes)
    {
        foreach ($processes as $key => $process) {
            $this->output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
            $this->output->writeln(sprintf('started %s', $process->getCommandLine()), OutputInterface::VERBOSITY_VERBOSE);

            $process->start(
                function ($type, $data) use ($key) {
                    foreach (explode(PHP_EOL, trim($data)) as $fe) {
                        $line = sprintf('%s [%s] %s', strtoupper($type), $key, trim($fe));
                        if ('err' === $type) {
                            $line = sprintf('<error>%s</error>', $line);
                        } else {
                            $line = sprintf('<info>%s</info>', $line);
                        }
                        $this->output->writeln($line);
                    }
                    $this->output->writeln('');
                }
            );

            $process->setTimeout(120);
            $process->setIdleTimeout(60);
        }

        if($count = count($processes)) {
            $this->output->writeln('');
            $this->output->writeln(
                sprintf(
                    'STARTED ... %s sek | %s processes [%s]',
                    date('i:s', $this->getWatch()->getDuration() / 1000),
                    $count,
                    implode('], [', array_keys($processes))
                )
            );
        }
    }

    /**
     * @return Process[]
     */
    protected function findStartableProcesses(): array
    {
        $processes = [];

        if ($startable = $this->startable()) {
            foreach ($this->processes as $key => $process) {
                if (!$process->isStarted()) {
                    $processes[$key] = $process;

                    if (0 >= --$startable) {
                        break;
                    }
                }
            }
        }

        return $processes;
    }

    /**
     * @return int
     */
    protected function startable(): int
    {
        $running = $terminated = 0;
        foreach ($this->processes as $key => $process) {
            if ($process->isRunning()) {
                ++$running;
            }

            try {
                $process->checkTimeout();
            } catch (ProcessTimedOutException $e) {
            }

            if ($process->isTerminated()) {
                ++$terminated;
            }
        }

        $open = count($this->processes) - $running - $terminated;

        //no negative value
        $parallel = max(0, $this->parallel);

        return 0 === $parallel ? $open : min($parallel - $running, $open);
    }

    /**
     * @param iterable|callable|Process|Process[] $processes
     *
     * @return Process[]
     */
    protected function prepareProcesses($processes): array
    {
        if (is_callable($processes)) {
            $processes = $processes();
        }
        if ($processes instanceof Process) {
            $processes = [$processes];
        } elseif ($processes instanceof \Traversable) {
            $processes = iterator_to_array($processes);
        }
        if (!is_array($processes)) {
            throw new \Exception('processes is not an array');
        }

        return $processes;
    }

    protected function getWatch(): StopwatchEvent
    {
        if (null === $this->watch) {
            $this->watch = new Stopwatch();
            $this->watch->start(self::class);
        }

        return $this->watch->getEvent(self::class);
    }

}