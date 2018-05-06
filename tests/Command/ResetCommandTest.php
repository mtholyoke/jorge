<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Command;

use MountHolyoke\Jorge\Command\ResetCommand;
// use MountHolyoke\Jorge\Jorge;
// use MountHolyoke\Jorge\Tool\LandoTool;
// use MountHolyoke\JorgeTests\Mock\MockConsoleOutput;
use MountHolyoke\JorgeTests\Mock\MockResetCommand;
use MountHolyoke\JorgeTests\Mock\MockJorge;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\ArrayInput;
// use Symfony\Component\Console\Output\OutputInterface;

final class ResetCommandTest extends TestCase {
  use OutputVerifierTrait;
  use RandomStringTrait;

  public function testConfigure(): void {
    $command = new ResetCommand();
    $this->assertSame('reset', $command->getName());
    $this->assertNotEmpty($command->getDescription());
    $definition = $command->getDefinition();
    $arguments = $definition->getArguments();
    $this->assertSame(0, count($arguments));
    $this->assertSame(0, $definition->getArgumentRequiredCount());
    $options = $definition->getOptions();
    $this->assertSame(5, count($options));
    $this->assertNotEmpty($command->getHelp());
    # testInitialize() checks $->params in a mock instance.
  }

  public function testInitialize(): void {
    $jorge = new MockJorge(getcwd());
    $output = $jorge->getOutput();

    $command = new MockResetCommand();
    $command->setName('mockReset');
    $this->assertSame(6, count($command->getParams()));
    $this->assertSame($command, $jorge->add($command));

    $appType = $this->makeRandomString();
    $reset = [
      'branch'   => $this->makeRandomString(),
      'database' => $this->makeRandomString(),
      'files'    => $this->makeRandomString(),
      'rsync'    => $this->makeRandomBoolean(),
      'username' => $this->makeRandomString(),
      'password' => $this->makeRandomString(),
    ];
    $jorge->setConfig([
      'appType' => $appType,
      'reset'   => $reset,
    ]);

    $input = new ArrayInput([]);
    $command->initialize($input, $output);

    # Make sure Jorge config is correctly applied:
    $this->assertSame($appType, $command->getAppType());
    $this->assertSame($reset, $command->getParams());
    $expect = [[LogLevel::DEBUG, '{mockReset} Parameters:']];
    foreach ($reset as $param => $value) {
      $expect[] = [LogLevel::DEBUG, sprintf("{mockReset}   %-8s => '%s'", $param, $value)];
    }
    $this->verifyMessages($expect, $command->messages);
    $command->messages = [];

    $options = [
      '--branch'   => $this->makeRandomString(),
      '--database' => $this->makeRandomString(),
      '--files'    => $this->makeRandomString(),
      '--username' => $this->makeRandomString(),
      '--password' => $this->makeRandomString(),
    ];
    $input = new ArrayInput($options);
    $command->initialize($input, $output);

    # Make sure command-line options are correctly applied:
    $config = [];
    foreach (array_keys($reset) as $key) {
      if ($key == 'rsync') {
        $config[$key] = $reset['rsync'];
      } else {
        $config[$key] = $options["--$key"];
      }
    }
    $this->assertSame($config, $command->getParams());
    $expect = [[LogLevel::DEBUG, '{mockReset} Parameters:']];
    foreach ($config as $param => $value) {
      $expect[] = [LogLevel::DEBUG, sprintf("{mockReset}   %-8s => '%s'", $param, $value)];
    }
    $this->verifyMessages($expect, $command->messages);
  }
}
