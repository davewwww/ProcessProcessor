<?php

namespace Dwo\ProcessProcessor;

use Symfony\Component\Process\Process;

class ProcessCollector
{
    /** @var Process[] */
    private $openProcesses = [];
    /** @var Process[] */
    private $runningProcesses = [];
    /** @var Process[] */
    private $terminatedProcesses = [];

    /**
     * @return Process[]
     */
    public function getOpenProcesses(): array
    {
        return $this->openProcesses;
    }

    /**
     * @return Process[]
     */
    public function getRunningProcesses(): array
    {
        return $this->runningProcesses;
    }

    /**
     * @return Process[]
     */
    public function getTerminatedProcesses(): array
    {
        return $this->terminatedProcesses;
    }

    /**
     * @param Process[] $processes
     */
    public function addProcesses(array $processes): void
    {
        foreach ($processes as $key => $process) {
            if (!isset($this->openProcesses[$key])) {
                $this->openProcesses[$key] = $process;
            } else {
                $this->openProcesses[] = $process;
            }
        }
    }

    public function running($key): void
    {
        if (isset($this->openProcesses[$key])) {
            $this->runningProcesses[$key] = $this->openProcesses[$key];
            unset($this->openProcesses[$key]);
        }
    }

    public function terminated($key): void
    {
        //try to run if it was not running
        $this->running($key);

        if (isset($this->runningProcesses[$key])) {
            $this->terminatedProcesses[$key] = $this->runningProcesses[$key];
            unset($this->runningProcesses[$key]);
        }
    }
}