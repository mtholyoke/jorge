<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Command;

use MountHolyoke\Jorge\Command\ResetCommand;
use MountHolyoke\Jorge\Jorge;
use MountHolyoke\Jorge\Tool\ComposerTool;
use MountHolyoke\Jorge\Tool\GitTool;
use MountHolyoke\Jorge\Tool\LandoTool;
use MountHolyoke\JorgeTests\Mock\MockConsoleOutput;
use MountHolyoke\JorgeTests\Mock\MockResetCommand;
use MountHolyoke\JorgeTests\Mock\MockJorge;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

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
    $this->assertSame(8, count($options));
    $this->assertNotEmpty($command->getHelp());
    # testInitialize() checks $->params in a mock instance.
  }

  public function testInitialize(): void {
    $jorge = new MockJorge(getcwd());
    $output = $jorge->getOutput();

    $command = new MockResetCommand();
    $command->setName('mockReset');
    $this->assertSame(7, count($command->getParams()));
    $this->assertSame($command, $jorge->add($command));

    $appType = $this->makeRandomString();
    $reset = [
      'auth'     => $this->makeRandomString(),
      'branch'   => $this->makeRandomString(),
      'import'   => $this->makeRandomBoolean(),
      'content'  => $this->makeRandomString(),
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
    $reset['database'] = $reset['content'];
    $reset['files']    = $reset['content'];
    $this->assertSame($reset, $command->getParams());
    $expect = [[LogLevel::DEBUG, '{mockReset} Parameters:']];
    foreach ($reset as $param => $value) {
      $expect[] = [LogLevel::DEBUG, sprintf("{mockReset}   %-8s => '%s'", $param, $value)];
    }
    $this->verifyMessages($expect, $command->messages);
    $command->messages = [];

    $options = [
      '--auth'     => $this->makeRandomString(),
      '--branch'   => $this->makeRandomString(),
      '--content'  => $this->makeRandomString(),
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
      if (in_array($key, ['import', 'rsync'])) {
        $config[$key] = $reset[$key];
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

  public function testInteract(): void {
    $jorge = new MockJorge(getcwd());
    $output = $jorge->getOutput();
    $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    $command = new MockResetCommand();
    $command->setName('mockReset');
    $this->assertSame($command, $jorge->add($command));

    $username = $this->makeRandomString();
    $jorge->setConfig(['reset' => ['username' => $username]]);
    $input = new ArrayInput([]);
    $command->initialize($input, $output);

    # Verify we're starting with no password set.
    $this->assertSame('', $command->getParams()['password']);

    # Set up two responses in the input stream.
    $password = $this->makeRandomString();
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, "\n" . $password);
    rewind($stream);
    $input->setStream($stream);

    # Make sure it's possible not to provide a password:
    $command->interact($input, $output);
    $this->assertNull($command->getParams()['password']);

    # Make sure it's possible to set a password:
    $command->interact($input, $output);
    $this->assertSame($password, $command->getParams()['password']);
  }

  /**
   * This checks only the appTypes that don’t actually do anything.
   */
  public function testExecute(): void {
    $jorge = new MockJorge(getcwd());
    $output = $jorge->getOutput();
    $command = new MockResetCommand();
    $command->setName('mockReset');
    $this->assertSame($command, $jorge->add($command));
    $input = new ArrayInput([]);

    $random = $this->makeRandomString();
    $responses = [
      'jorge' => [LogLevel::WARNING, 'Can’t reset self'],
      ''      => [LogLevel::ERROR,   'No application type specified'],
      $random => [LogLevel::ERROR,   'Unrecognized application type "{%appType}"'],
    ];

    foreach ($responses as $appType => $response) {
      $jorge->setConfig(['appType' => $appType]);
      $command->initialize($input, $output);
      $command->messages = [];
      # execute() should return a 1 because it failed:
      $this->assertSame(1, $command->execute($input, $output));
      $response[1] = '{mockReset} ' . $response[1];
      $this->verifyMessages([$response], $command->messages);
    }
  }

  public function testExecuteDrupal7(): void {
    # Set up params for a couple of runs:
    $simpleParams = [
      'auth'     => '',
      'branch'   => $this->makeRandomString(),
      'import'   => FALSE,
      'content'  => $this->makeRandomString(),
      'rsync'    => FALSE,
      'username' => '',
      'password' => '',
    ];
    $complexParams = [
      'auth'     => $this->makeRandomString(),
      'branch'   => $this->makeRandomString(),
      'import'   => TRUE,
      'database' => $this->makeRandomString(),
      'files'    => $this->makeRandomString(),
      'rsync'    => TRUE,
      'username' => $this->makeRandomString(),
      'password' => $this->makeRandomString(),
    ];

    # Set up Git tool:
    $cleanFalse = (object) ['clean' => FALSE];
    $cleanTrue  = (object) ['clean' => TRUE];
    $git = $this->getMockBuilder(GitTool::class)
                ->setMethods(['getStatus', 'run'])
                ->getMock();
    $git->expects($this->exactly(4))
        ->method('getStatus')
        ->will($this->onConsecutiveCalls($cleanFalse, $cleanTrue, $cleanTrue, $cleanTrue));
    $git->expects($this->exactly(6))
        ->method('run')
        ->withConsecutive(
          $this->equalTo(['checkout', $simpleParams['branch']]),
          $this->equalTo(['pull']),
          $this->equalTo(['checkout', $simpleParams['branch']]),
          $this->equalTo(['pull']),
          $this->equalTo(['checkout', $complexParams['branch']]),
          $this->equalTo(['pull'])
        )
        ->willReturn(0);

    # Set up Lando tool:
    $lando = $this->getMockBuilder(LandoTool::class)
                  ->setMethods(['needsAuth', 'requireStarted', 'run'])
                  ->getMock();
    $lando->expects($this->exactly(3))
          ->method('needsAuth')
          ->will($this->onConsecutiveCalls(FALSE, TRUE, TRUE));
    $lando->expects($this->exactly(3))
          ->method('requireStarted')
          ->willReturn(TRUE);
    $lando->expects($this->exactly(2))
          ->method('run')
          ->withConsecutive(
              ['pull --code=none --database=' . $simpleParams['content'] . ' --files=' . $simpleParams['content']],
              ['pull --code=none --database=' . $complexParams['database'] . ' --files=' . $complexParams['files'] . ' --rsync --auth=' . $complexParams['auth']]
            )
          ->willReturn(0);

    # Set up Drush command:
    $drush = $this->getMockBuilder(DrushCommand::class)
                  ->setMethods(['run'])
                  ->getMock();
    $drush->expects($this->exactly(6))
          ->method('run')
          ->willReturn(0);

    # Set up Jorge:
    $jorge = $this->getMockBuilder(Jorge::class)
                  ->setMethods(['find', 'getPath', 'getTool'])
                  ->getMock();
    $jorge->expects($this->exactly(2))
          ->method('find')
          ->with($this->equalTo('drush'))
          ->willReturn($drush);
    $jorge->expects($this->exactly(4))
          ->method('getPath')
          ->withConsecutive(
              [$this->equalTo(''), $this->isFalse()],
              [$this->equalTo(''), $this->isFalse()],
              [$this->equalTo(''), $this->isFalse()],
              [$this->equalTo(''), $this->isFalse()]
            )
          ->willReturn(getcwd());
    $jorge->expects($this->exactly(8))
          ->method('getTool')
          ->withConsecutive(
              $this->equalTo('git'), $this->equalTo('lando'),
              $this->equalTo('git'), $this->equalTo('lando'),
              $this->equalTo('git'), $this->equalTo('lando'),
              $this->equalTo('git'), $this->equalTo('lando')
            )
          ->will($this->onConsecutiveCalls(
              $git, $lando,
              $git, $lando,
              $git, $lando,
              $git, $lando
            ));

    # Set up the command with default params:
    $command = new MockResetCommand();
    $command->setAppType('drupal7');
    $command->setName('mockReset');
    $command->setJorge($jorge);
    $input = new ArrayInput([]);
    $output = new MockConsoleOutput($jorge, OutputInterface::VERBOSITY_NORMAL);

    # Run without a clean working directory:
    $this->assertSame(1, $command->execute($input, $output));
    $expect = [
      [LogLevel::ERROR, '{mockReset} Working directory not clean. Aborting.'],
    ];
    $this->verifyMessages($expect, $command->messages);
    $command->messages = [];

    # Run without rsync or username/$password:
    $command->setParams($simpleParams);
    $this->assertNull($command->execute($input, $output));
    $this->verifyMessages([], $command->messages);

    # Same params should fail when missing required auth:
    $this->assertSame(1, $command->execute($input, $output));
    $expect = [
      [LogLevel::ERROR, '{mockReset} This version of Lando requires an auth token to pull. Aborting.'],
    ];
    $this->verifyMessages($expect, $command->messages);
    $command->messages = [];

    # Run with rsync and username/$password:
    $command->setParams($complexParams);
    $this->assertNull($command->execute($input, $output));
    $this->verifyMessages([], $command->messages);
  }

  public function testExecuteDrupal8(): void {
    # Set up params for a couple of runs:
    $simpleParams = [
      'auth'     => '',
      'branch'   => $this->makeRandomString(),
      'import'   => FALSE,
      'content'  => $this->makeRandomString(),
      'rsync'    => FALSE,
      'username' => '',
      'password' => '',
    ];
    $complexParams = [
      'auth'     => $this->makeRandomString(),
      'branch'   => $this->makeRandomString(),
      'import'   => TRUE,
      'database' => $this->makeRandomString(),
      'files'    => $this->makeRandomString(),
      'rsync'    => TRUE,
      'username' => $this->makeRandomString(),
      'password' => $this->makeRandomString(),
    ];

    # Set up Composer tool:
    $compo = $this->getMockBuilder(ComposerTool::class)
                  ->setMethods(['run'])
                  ->getMock();
    $compo->expects($this->exactly(3))
          ->method('run')
          ->with(['command' => 'install'])
          ->willReturn(0);

    # Set up Git tool:
    $cleanFalse = (object) ['clean' => FALSE];
    $cleanTrue  = (object) ['clean' => TRUE];
    $git = $this->getMockBuilder(GitTool::class)
                ->setMethods(['getStatus', 'run'])
                ->getMock();
    $git->expects($this->exactly(4))
        ->method('getStatus')
        ->will($this->onConsecutiveCalls($cleanFalse, $cleanTrue, $cleanTrue, $cleanTrue));
    $git->expects($this->exactly(6))
        ->method('run')
        ->withConsecutive(
          $this->equalTo(['checkout', $simpleParams['branch']]),
          $this->equalTo(['pull']),
          $this->equalTo(['checkout', $simpleParams['branch']]),
          $this->equalTo(['pull']),
          $this->equalTo(['checkout', $complexParams['branch']]),
          $this->equalTo(['pull'])
        )
        ->willReturn(0);

    # Set up Lando tool:
    $lando = $this->getMockBuilder(LandoTool::class)
                  ->setMethods(['getConfig', 'needsAuth', 'requireStarted', 'run'])
                  ->getMock();
    $lando->expects($this->exactly(3))
          ->method('getConfig')
          ->with('tooling')
          ->will($this->onConsecutiveCalls(NULL, NULL, ['ssh-agent-pull' => TRUE]));
    $lando->expects($this->exactly(3))
          ->method('needsAuth')
          ->will($this->onConsecutiveCalls(FALSE, TRUE, TRUE));
    $lando->expects($this->exactly(3))
          ->method('requireStarted')
          ->willReturn(TRUE);
    $lando->expects($this->exactly(2))
          ->method('run')
          ->withConsecutive(
              ['pull --code=none --database=' . $simpleParams['content'] . ' --files=' . $simpleParams['content']],
              ['ssh-agent-pull --code=none --database=' . $complexParams['database'] . ' --files=' . $complexParams['files'] . ' --rsync --auth=' . $complexParams['auth']]
            )
          ->willReturn(0);

    # Set up Drush command:
    $drush = $this->getMockBuilder(DrushCommand::class)
                  ->setMethods(['run'])
                  ->getMock();
    $drush->expects($this->exactly(11))
          ->method('run')
          ->willReturn(0);

    # Set up Jorge:
    $jorge = $this->getMockBuilder(Jorge::class)
                  ->setMethods(['find', 'getPath', 'getTool'])
                  ->getMock();
    $jorge->expects($this->exactly(2))
          ->method('find')
          ->with($this->equalTo('drush'))
          ->willReturn($drush);
    $jorge->expects($this->exactly(4))
          ->method('getPath')
          ->withConsecutive(
              [$this->equalTo(''), $this->isFalse()],
              [$this->equalTo(''), $this->isFalse()],
              [$this->equalTo(''), $this->isFalse()],
              [$this->equalTo(''), $this->isFalse()]
            )
          ->willReturn(getcwd());
    $jorge->expects($this->exactly(12))
          ->method('getTool')
          ->withConsecutive(
              $this->equalTo('composer'), $this->equalTo('git'), $this->equalTo('lando'),
              $this->equalTo('composer'), $this->equalTo('git'), $this->equalTo('lando'),
              $this->equalTo('composer'), $this->equalTo('git'), $this->equalTo('lando'),
              $this->equalTo('composer'), $this->equalTo('git'), $this->equalTo('lando')
            )
          ->will($this->onConsecutiveCalls(
              $compo, $git, $lando,
              $compo, $git, $lando,
              $compo, $git, $lando,
              $compo, $git, $lando
            ));

    # Set up the command with default params:
    $command = new MockResetCommand();
    $command->setAppType('drupal8');
    $command->setName('mockReset');
    $command->setJorge($jorge);
    $input = new ArrayInput([]);
    $output = new MockConsoleOutput($jorge, OutputInterface::VERBOSITY_NORMAL);

    # Run without a clean working directory:
    $this->assertSame(1, $command->execute($input, $output));
    $expect = [
      [LogLevel::ERROR, '{mockReset} Working directory not clean. Aborting.'],
    ];
    $this->verifyMessages($expect, $command->messages);
    $command->messages = [];

    # Run without rsync or username/$password:
    $command->setParams($simpleParams);
    $this->assertNull($command->execute($input, $output));
    $this->verifyMessages([], $command->messages);

    # Same params should fail when missing required auth:
    $this->assertSame(1, $command->execute($input, $output));
    $expect = [
      [LogLevel::ERROR, '{mockReset} This version of Lando requires an auth token to pull. Aborting.'],
    ];
    $this->verifyMessages($expect, $command->messages);
    $command->messages = [];

    # Run with rsync and username/$password:
    $command->setParams($complexParams);
    $this->assertNull($command->execute($input, $output));
    $this->verifyMessages([], $command->messages);
  }
}
