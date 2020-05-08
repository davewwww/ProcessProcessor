<?php

namespace Dwo\ProcessProcessor\Tests;

use Dwo\ProcessProcessor\CommandLineHelper;
use PHPUnit\Framework\TestCase;

class CommandLineHelperTest extends TestCase
{

    public function testConsole()
    {
       $cmd = CommandLineHelper::console('foo:bar');

        self::assertStringContainsString('foo:bar', $cmd);
    }
}