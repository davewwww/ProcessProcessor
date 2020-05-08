<?php

namespace Dwo\ProcessProcessor\Tests;

use Dwo\ProcessProcessor\ProcessCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProcessCollectorTest extends TestCase
{
    /** @var ProcessCollector */
    private $collector;

    public function setUp(): void
    {
        $this->collector = new ProcessCollector();
    }

    public function testAdd()
    {
        $this->collector->addProcesses([Process::fromShellCommandline('')]);
        $this->collector->addProcesses([Process::fromShellCommandline('')]);

        self::assertCount(2, $this->collector->getOpenProcesses());
        self::assertEquals([0, 1], array_keys($this->collector->getOpenProcesses()));
    }

    public function testAddAndGet()
    {
        $this->collector->addProcesses([$p1 = Process::fromShellCommandline('')]);

        self::assertEquals([$p1], $this->collector->getOpenProcesses());
    }

    public function testRunning()
    {
        $this->collector->addProcesses([$p1 = Process::fromShellCommandline('')]);
        $this->collector->running(0);

        self::assertEquals([], $this->collector->getOpenProcesses());
        self::assertEquals([$p1], $this->collector->getRunningProcesses());
    }

    public function testRunningUnknownKey()
    {
        $this->collector->addProcesses([$p1 = Process::fromShellCommandline('')]);
        $this->collector->running('foo');

        self::assertEquals([$p1], $this->collector->getOpenProcesses());
        self::assertEquals([], $this->collector->getRunningProcesses());
    }

    public function testTerminate()
    {
        $this->collector->addProcesses([$p1 = Process::fromShellCommandline('')]);
        $this->collector->running(0);

        self::assertEquals([], $this->collector->getOpenProcesses());
        self::assertEquals([$p1], $this->collector->getRunningProcesses());
        self::assertEquals([], $this->collector->getTerminatedProcesses());

        $this->collector->terminated(0);

        self::assertEquals([], $this->collector->getOpenProcesses());
        self::assertEquals([], $this->collector->getRunningProcesses());
        self::assertEquals([$p1], $this->collector->getTerminatedProcesses());
    }

    public function testTerminateWithoutRunning()
    {
        $this->collector->addProcesses([$p1 = Process::fromShellCommandline('')]);
        $this->collector->terminated(0);

        self::assertEquals([], $this->collector->getOpenProcesses());
        self::assertEquals([], $this->collector->getRunningProcesses());
        self::assertEquals([$p1], $this->collector->getTerminatedProcesses());
    }

    public function testTerminateUnknownKey()
    {
        $this->collector->addProcesses([$p1 = Process::fromShellCommandline('')]);
        $this->collector->terminated('foo');

        self::assertEquals([$p1], $this->collector->getOpenProcesses());
        self::assertEquals([], $this->collector->getRunningProcesses());
        self::assertEquals([], $this->collector->getTerminatedProcesses());
    }

    public function test()
    {
        $this->collector = new ProcessCollector();

        $this->collector->addProcesses(
            [
                '1' => $p1 = Process::fromShellCommandline(''),
                '2' => $p2 = Process::fromShellCommandline(''),
                '3' => $p3 = Process::fromShellCommandline(''),
                '4' => $p4 = Process::fromShellCommandline(''),
                '5' => $p5 = Process::fromShellCommandline(''),
                '6' => $p5 = Process::fromShellCommandline(''),
            ]
        );
        $this->collector->running('1');
        $this->collector->terminated('1');
        $this->collector->terminated('2');
        $this->collector->running('3');
        $this->collector->running('4');

        self::assertEquals(['5','6'], array_keys($this->collector->getOpenProcesses()));
        self::assertEquals(['3','4'], array_keys($this->collector->getRunningProcesses()));
        self::assertEquals(['1','2'], array_keys($this->collector->getTerminatedProcesses()));
    }
}