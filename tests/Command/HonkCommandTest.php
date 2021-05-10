<?php

declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Command;

use MountHolyoke\Jorge\Command\HonkCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class HonkCommandTest extends TestCase
{
    public function testConfigure(): void
    {
        $honk = new HonkCommand();
        $this->assertSame('honk', $honk->getName());
    }

    public function testExecute(): void
    {
        $honk = new HonkCommand();
        $input = new ArgvInput();
        $output = new BufferedOutput();

        // The test string contains ^G for a beep.
        $expect = 'Honk!' . PHP_EOL;
        $response = $honk->run($input, $output);
        $this->assertSame(0, $response);
        $this->assertSame($expect, $output->fetch());
    }
}
