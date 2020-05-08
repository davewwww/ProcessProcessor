<?php

namespace Dwo\ProcessProcessor;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\PhpExecutableFinder;

class CommandLineHelper
{
    /**
     * @param Command      $command
     * @param string|array $args
     * @param array        $options
     *
     * @return string
     */
    public static function command(Command $command, $args = [], $options = []): string
    {
        $cmdline = array_merge(
            (array) $command::getDefaultName(),
            (array) $args,
            (array) $options
        );

        return self::console(implode(' ', $cmdline));
    }

    public static function console(string $command, string $console = null): string
    {
        $console = $console ?? $_SERVER['PHP_SELF'] ?? 'bin/console';

        #$console = $_SERVER['SYMFONY_CONSOLE_FILE'] ?? $_SERVER['argv'][0];

        return self::php($console.' '.$command);
    }

    public static function php(string $command): string
    {
        $executableFinder = new PhpExecutableFinder();
        if (false === $exec = $executableFinder->find(false)) {
            throw new \Exception('php not found');
        }

        return $exec.implode(' ', $executableFinder->findArguments()).' '.$command;
    }

}