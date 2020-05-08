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
    /** @var OutputInterface|null */
    private $output;
    /** @var int */
    private $concurrent;
    /** @var ProcessCollector */
    private $collector;
    /** @var callable|null */
    private $onTerminated;
    /** @var Stopwatch|null */
    private $watch;
    /** @var int|null */
    private $lastTick = null;

    /**
     * @param OutputInterface|null $output
     * @param int                  $concurrent
     */
    public function __construct(OutputInterface $output = null, int $concurrent = 10)
    {
        $this->concurrent = max(0, $concurrent);
        $this->output = $output ?? new NullOutput();
        $this->collector = new ProcessCollector();
    }

    /**
     * @param callable|null $onTerminated
     */
    public function setOnTerminated(?callable $onTerminated): void
    {
        $this->onTerminated = $onTerminated;
    }

    /**
     * @param Process[] $processes
     * @param bool      $start
     *
     * @return QueuedProcessor
     */
    public function addProcesses(array $processes, bool $start = false): self
    {
        $this->collector->addProcesses($processes);

        if ($start) {
            $this->start();
        }

        return $this;
    }

    public function wait(): void
    {
        do {
            $this->start();

            $this->tick(0);

            if ($countOpen = count($this->collector->getOpenProcesses())) {
                sleep(1);
            }
        } while ($countOpen);

    }

    public function tick(int $diff = 1): void
    {
        //only one tick per sec
        if (0 !== $diff && time() - $this->lastTick < $diff) {
            return;
        }

        //check terminated processes, unset process and call onTerminated
        foreach ($this->collector->getRunningProcesses() as $key => $process) {
            try {
                $process->checkTimeout();
            } catch (ProcessTimedOutException $e) {
            }

            if ($process->isTerminated()) {
                $this->collector->terminated($key);

                if (null !== $this->onTerminated) {
                    ($this->onTerminated)($key, $process);
                }
            }
        }

        $this->outputTick();

        $this->lastTick = time();
    }

    protected function start(): void
    {
        $open = count($this->collector->getOpenProcesses());
        $running = count($this->collector->getRunningProcesses());

        $startable = 0 === $this->concurrent ? $open : max(min($this->concurrent - $running, $open), 0);

        if (0 === $startable) {
            return;
        }

        $started = [];

        foreach ($this->collector->getOpenProcesses() as $key => $process) {

            $this->collector->running($key);

            if (!$process->isStarted()) {
                $started[$key] = $process;

                $this->output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
                $this->output->writeln(sprintf('started %s', $process->getCommandLine()), OutputInterface::VERBOSITY_VERBOSE);

                $process->start(
                    function ($type, $data) use ($key) {
                        $this->outputRunningCallback($type, $data, $key);
                    }
                );

                if (0 >= --$startable) {
                    break;
                }
            }
        }

        $this->outputStarted($started);
    }

    private function outputTick()
    {
        $countLeft = count($this->collector->getOpenProcesses());
        $countRunning = count($this->collector->getRunningProcesses());
        $countTerminated = count($this->collector->getTerminatedProcesses());
        $countAll = $countLeft + $countRunning + $countTerminated;

        $this->output->writeln(
            sprintf(
                '%s ... %s sek | %s/%s done',
                $countLeft ? 'WAITING' : ' =DONE=',
                date('i:s', $this->getStopwatchEvent()->getDuration() / 1000),
                $countAll - $countLeft,
                $countAll
            )
        );
    }

    private function outputRunningCallback($type, $data, $key): void
    {
        foreach (explode(PHP_EOL, trim($data)) as $outputLine) {
            $line = sprintf('%s [%s] %s', strtoupper($type), $key, trim($outputLine));
            if ('err' === $type) {
                $line = sprintf('<error>%s</error>', $line);
            } else {
                $line = sprintf('<info>%s</info>', $line);
            }
            $this->output->writeln($line);
        }
        $this->output->writeln('');
    }

    private function outputStarted(array $processes)
    {
        if ($processes) {
            $this->output->writeln('');
            $this->output->writeln(
                sprintf(
                    'STARTED ... %s sek | %s processes [%s]',
                    date('i:s', $this->getStopwatchEvent()->getDuration() / 1000),
                    count($processes),
                    implode('], [', array_keys($processes))
                )
            );
        }
    }

    protected function getStopwatchEvent(): StopwatchEvent
    {
        if (null === $this->watch) {
            $this->watch = new Stopwatch();
            $this->watch->start(self::class);
        }

        return $this->watch->getEvent(self::class);
    }

}