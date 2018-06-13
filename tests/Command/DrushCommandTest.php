<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Command;

use MountHolyoke\Jorge\Command\DrushCommand;
use MountHolyoke\Jorge\Jorge;
use MountHolyoke\Jorge\Tool\LandoTool;
use MountHolyoke\JorgeTests\Mock\MockConsoleOutput;
use MountHolyoke\JorgeTests\Mock\MockDrushCommand;
use MountHolyoke\JorgeTests\Mock\MockJorge;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

final class DrushCommandTest extends TestCase {
  use OutputVerifierTrait;
  use RandomStringTrait;

  public function testConfigure(): void {
    $command = new DrushCommand();
    $this->assertSame('drush', $command->getName());
    $this->assertNotEmpty($command->getDescription());
    $definition = $command->getDefinition();
    $arguments = $definition->getArguments();
    $this->assertSame(1, count($arguments));
    $this->assertSame(0, $definition->getArgumentRequiredCount());
    $options = $definition->getOptions();
    $this->assertSame(1, count($options));
    $this->assertNotEmpty($command->getHelp());
  }

  public function testExecute(): void {
    $dc = $this->makeRandomString();

    $lando = $this->getMockBuilder(LandoTool::class)
                  ->setMethods(['isEnabled', 'requireStarted', 'run'])
                  ->getMock();
    $lando->expects($this->exactly(2))
          ->method('isEnabled')
          ->will($this->onConsecutiveCalls(FALSE, TRUE));
    $lando->expects($this->once())
          ->method('requireStarted')
          ->willReturn(TRUE);
    $lando->expects($this->once())
          ->method('run')
          ->with($this->equalTo('drush ' . $dc))
          ->willReturn(0);

    $jorge = $this->getMockBuilder(Jorge::class)
                  ->setMethods(['getConfig', 'getPath', 'getTool'])
                  ->getMock();
    $jorge->expects($this->exactly(2))
          ->method('getConfig')
          ->with($this->equalTo('appType'))
          ->willReturn('');
    $jorge->expects($this->exactly(2))
          ->method('getPath')
          ->with($this->equalTo(''), $this->isTrue())
          ->willReturn(getcwd());
    $jorge->expects($this->exactly(2))
          ->method('getTool')
          ->with($this->equalTo('lando'))
          ->willReturn($lando);

    $command = new MockDrushCommand();
    $command->setJorge($jorge);
    $command->setDrushCommand($dc);

    $input = new ArrayInput([]);
    $output = new MockConsoleOutput($jorge, OutputInterface::VERBOSITY_NORMAL);

    # First, test with Lando disabled:
    $expect = [[LogLevel::ERROR, '{drush} Cannot run without Lando']];
    $this->assertNull($command->execute($input, $output));
    $this->verifyMessages($expect, $command->messages);
    $command->messages = [];

    # Next, test with Lando enabled:
    $this->assertSame(0, $command->execute($input, $output));
    $this->verifyMessages([], $command->messages);
  }

  public function testFindDrupal(): void {
    $randomType = $this->makeRandomString();
    $cwd = getcwd();

    $jorge = $this->getMockBuilder(Jorge::class)
                  ->setMethods(['getConfig', 'getPath'])
                  ->getMock();
    $jorge->expects($this->exactly(3))
          ->method('getConfig')
          ->with($this->equalTo('appType'))
          ->will($this->onConsecutiveCalls('drupal7', 'drupal8', $randomType));
    $jorge->expects($this->exactly(3))
          ->method('getPath')
          ->withConsecutive(
            [$this->equalTo(''), $this->isTrue()],
            [$this->equalTo('web'), $this->isTrue()],
            [$this->equalTo(''), $this->isTrue()]
          )
          ->will($this->onConsecutiveCalls($cwd, $cwd . '/web', $cwd));

    $command = new MockDrushCommand();
    $command->setJorge($jorge);

    $this->assertSame($cwd, $command->findDrupal());
    $this->assertSame($cwd . '/web', $command->findDrupal());
    $this->assertSame($cwd, $command->findDrupal());
  }

  public function testInitialize(): void {
    $jorge = new MockJorge(getcwd());
    $output = $jorge->getOutput();

    $command = new MockDrushCommand();
    $command->setName('mockDrush');
    $this->assertSame($command, $jorge->add($command));

    $dc = $this->makeRandomString();
    $input = new ArrayInput(['drush_command' => [$dc]]);
    $command->initialize($input, $output);
    $this->assertSame($dc, $command->getDrushCommand());

    $dc = $this->makeRandomString();
    $input = new ArrayInput(['drush_command' => [$dc, '-y']]);
    $command->initialize($input, $output);
    $this->assertSame("$dc -y", $command->getDrushCommand());

    $dc = $this->makeRandomString();
    $input = new ArrayInput(['drush_command' => [$dc], '--yes' => TRUE]);
    $command->initialize($input, $output);
    $this->assertSame("$dc --yes", $command->getDrushCommand());

    $dc = $this->makeRandomString();
    $input = new ArrayInput(['drush_command' => [$dc, '-n']]);
    $command->initialize($input, $output);
    $this->assertSame("$dc -n", $command->getDrushCommand());

    $dc = $this->makeRandomString();
    $input = new ArrayInput(['drush_command' => [$dc], '--no-interaction' => TRUE]);
    $command->initialize($input, $output);
    $this->assertSame("$dc --no", $command->getDrushCommand());

    $dc = $this->makeRandomString();
    $input = new ArrayInput(['drush_command' => [$dc]]);
    $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
    $command->initialize($input, $output);
    $this->assertSame("$dc --verbose", $command->getDrushCommand());
  }
}
