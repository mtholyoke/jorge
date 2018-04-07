<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Command;

use MountHolyoke\Jorge\Command\HonkCommand;
use MountHolyoke\JorgeTests\Mock\MockJorge;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;

final class HonkCommandTest extends TestCase {
  use OutputVerifierTrait;

  public function testConfigure(): void {
    $honk = new HonkCommand();
    $this->assertSame('honk', $honk->getName());
  }

  public function testExecute(): void {
    // This requires a MockJorge so we can capture output.
    $jorge = new MockJorge(__DIR__);
    $input = new ArgvInput();
    $output = $jorge->getOutput();

    $honk = new HonkCommand();
    $response = $honk->run($input, $output);

    $this->assertSame(0, $response);
    $this->verifyMessages([['writeln', 'Honk!']], $jorge->messages);
  }
}
