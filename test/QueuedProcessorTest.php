<?php

namespace Dwo\ProcessProcessor\Tests;

use Dwo\ProcessProcessor\QueuedProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class QueuedProcessorTest extends TestCase
{
    private static $phpBin;
    private static $process;
    private static $sigchild;
    private $processes;

    public static function setUpBeforeClass():void
    {
        $phpBin = new PhpExecutableFinder();
        self::$phpBin = getenv('SYMFONY_PROCESS_PHP_TEST_BINARY') ?: ('phpdbg' === \PHP_SAPI ? 'php' : $phpBin->find());

        ob_start();
        phpinfo(INFO_GENERAL);
        self::$sigchild = false !== strpos(ob_get_clean(), '--enable-sigchild');
    }

    public function setUp():void {
        $this->processes = [
            '1' => Process::fromShellCommandline(sprintf('%s -r %s',self::$phpBin,'sleep(5);echo "1";')),
            '2' => Process::fromShellCommandline(sprintf('%s -r %s',self::$phpBin,'usleep(20000);echo "2";')),
            '3' => Process::fromShellCommandline('echo 3'),
            '6' => Process::fromShellCommandline('echo 6'),
            '4' => Process::fromShellCommandline('echo 4'),
            '5' => Process::fromShellCommandline('echo 5'),
            '7' => Process::fromShellCommandline(sprintf('%s -r %s',self::$phpBin,'usleep(20000);echo "7";')),
            '8' => Process::fromShellCommandline('echo 8'),
        ];
    }

    public function testTick()
    {
        $processor = new QueuedProcessor($output = new BufferedOutput());
        $processor->addProcesses($this->processes);

        $processor->tick();

        $result = $output->fetch();
        self::assertStringContainsString('WAITING ... 00:00 sek | 0/8 done', $result);
    }

    public function test()
    {
        $processor = new QueuedProcessor($output = new BufferedOutput());
        $processor->addProcesses($this->processes);

        $processor->tick();

        $result = $output->fetch();



        self::assertStringContainsString('STARTED ... 00:00 sek | 6 processes [1], [2], [3], [4], [5], [6]', $result);
        self::assertStringContainsString('OUT [1] 1', $result);
        self::assertStringContainsString('OUT [2] 2', $result);
        self::assertStringContainsString('OUT [3] 3', $result);
        self::assertStringContainsString('OUT [4] 4', $result);
        self::assertStringContainsString('OUT [5] 5', $result);
        self::assertStringContainsString('OUT [6] 6', $result);
        self::assertStringContainsString('=DONE= ... 00:00 sek | 8/8 done', $result);
    }
}