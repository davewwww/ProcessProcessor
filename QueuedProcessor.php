<?php

namespace Dwo\ProcessProcessor;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Component\Stopwatch\Stopwatch;

class QueuedProcessor
{
    /** @var OutputInterface */
    private $output;

    /**
     * QueuedProcessor constructor.
     *
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output = null)
    {
        $this->output = $output ?? new NullOutput();
    }

    /**
     * @param iterable|callable|Process|Process[] $processes
     * @param int                                 $parallel
     * @param callable|null                       $onTerminated
     */
    public function wait($processes, int $parallel = 10, callable $onTerminated = null): void
    {
        $watch = new Stopwatch();
        $watch->start('all');

        $countAll = count($processes = $this->prepareProcesses($processes));

        do {
            foreach ($startable = $this->findStartableProcesses($processes, $parallel) as $key => $process) {

                //commandline output
                $this->output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
                $this->output->writeln(sprintf('started %s', $process->getCommandLine()), OutputInterface::VERBOSITY_VERBOSE);

                $process->start(
                    function ($type, $data) use ($key) {
                        foreach(explode(PHP_EOL,trim($data)) as $fe) {
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

            if ($startable) {
                $this->output->writeln('');
                $this->output->writeln(
                    sprintf(
                        'STARTED ... %s sek | %s processes [%s]',

                        date('i:s', $watch->getEvent('all')->getDuration() / 1000),
                        count($startable),
                        implode('], [', array_keys($startable))
                    )
                );
            }

            foreach ($processes as $key => $process) {
                if ($process->isTerminated()) {
                    unset($processes[$key]);

                    if (null !== $onTerminated) {
                        $onTerminated($key, $process);
                    }
                }
            }

            $countLeft = count($processes);

            $this->output->writeln(
                sprintf(
                    '%s ... %s sek | %s/%s done',
                    $countLeft ? 'WAITING' : ' =DONE=',
                    date('i:s', $watch->getEvent('all')->getDuration() / 1000),
                    $countAll - $countLeft,
                    $countAll
                )
            );

            if ($countLeft) {
                sleep(1);
            }
        } while ($countLeft);
    }

    /**
     * @param Process[] $processes
     * @param int       $parallel
     *
     * @return Process[]
     */
    protected function findStartableProcesses(array $processes, int $parallel): array
    {
        $startable = $this->startable($processes, $parallel);

        $started = [];
        foreach ($processes as $key => $process) {
            if (!$process->isStarted()) {
                $started[$key] = $process;

                if (0 === --$startable) {
                    break;
                }
            }
        }

        return $started;
    }


    /**
     * @param Process[] $processes
     * @param int       $parallel
     *
     * @return int
     */
    protected function startable(array $processes, int $parallel): int
    {
        $running = $terminated = 0;
        foreach ($processes as $key => $process) {
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

        $open = count($processes) - $running - $terminated;

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
}