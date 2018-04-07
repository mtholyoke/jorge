<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Tool;

use MountHolyoke\Jorge\Tool\ComposerTool;
use MountHolyoke\JorgeTests\Mock\MockComposerTool;
use MountHolyoke\JorgeTests\Mock\MockJorge;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Seld\JsonLint\ParsingException;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test the functionality of Tool that isn’t covered elsewhere.
 */
final class ComposerToolTest extends TestCase {
  use OutputVerifierTrait;
  use RandomStringTrait;

  public function testInitializeWithoutRoot(): void {
    $tempDir = (new TemporaryDirectory())->create();
    $root = realpath($tempDir->path());
    chdir($root);
    $jorge = new MockJorge($root);
    $jorge->configure();

    # Verify that initalize() returned without enabling the ComposerTool.
    $tool = $jorge->getTool('composer');
    $this->assertFalse($tool->isEnabled());
    $tempDir->delete();
  }

  public function testInitializeWithoutComposerJson(): void {
    $tempDir = (new TemporaryDirectory())->create();
    $root = realpath($tempDir->path());
    mkdir($root . DIRECTORY_SEPARATOR . '.jorge');
    chdir($root);
    $jorge = new MockJorge($root);
    $jorge->configure();

    # Verify that initalize() returned without enabling the ComposerTool.
    $tool = $jorge->getTool('composer');
    $this->assertFalse($tool->isEnabled());
    $tempDir->delete();
  }

  public function testInitializeWithEmptyComposerJson(): void {
    $tempDir = (new TemporaryDirectory())->create();
    $root = realpath($tempDir->path());
    mkdir($root . DIRECTORY_SEPARATOR . '.jorge');
    chdir($root);
    touch('composer.json');
    $jorge = new MockJorge($root);
    $this->expectException(ParsingException::class);
    $jorge->configure();
    $tempDir->delete();
  }

  public function testInitializeWithValidComposerJson(): void {
    $tempDir = (new TemporaryDirectory())->create();
    $root = realpath($tempDir->path());
    mkdir($root . DIRECTORY_SEPARATOR . '.jorge');
    chdir($root);
    $config = ['name' => 'test/' . $this->makeRandomString()];
    file_put_contents('composer.json', json_encode($config));
    $jorge = new MockJorge($root);
    $jorge->configure();
    $tool = $jorge->getTool('composer');
    $this->assertTrue($tool->isEnabled());
    $tempDir->delete();
  }

  public function testExec(): void {
    $jorge = new MockJorge(getcwd());
    $jorge->getOutput()->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    $jorge->configure();
    $startup = [
      [LogLevel::NOTICE, 'Project root: {%root}'],
      [LogLevel::DEBUG,  '{composer} Executable is "{%executable}"'],
      [LogLevel::DEBUG,  '{git} Executable is "{%executable}"'],
      [LogLevel::NOTICE, '{git} $ {%command}'],
      [LogLevel::DEBUG,  '{lando} Executable is "{%executable}"'],
      ['NULL',           'Can’t read config file {%filename}'],
    ];
    $this->verifyMessages($startup, $jorge->messages);
    $jorge->messages = [];

    $tool = new MockComposerTool();
    $jorge->addTool($tool);
    $tool->mockComposerApplication($this);
    $expect = [[
      LogLevel::ERROR,
      '{mockComposer} Cannot set executable "{%executable}"',
      ['%executable' => 'mockComposer']
    ]];
    $this->verifyMessages($expect, $jorge->messages, TRUE);
    $jorge->messages = [];

    # Make sure an empty run succeeds.
    $status = $tool->runThis();
    $expect = [[
      LogLevel::NOTICE,
      '{mockComposer} % composer {%cmd} {%argv}',
      ['%cmd' => '', '%argv' => '-q']
    ]];
    $this->assertSame(0, $status);
    $this->verifyMessages($expect, $jorge->messages, TRUE);
    $jorge->messages = [];

    # Make sure a command run also succeeds.
    $name = $this->makeRandomString();
    $status = $tool->runThis(['command' => $name]);
    $expect = [[
      LogLevel::NOTICE,
      '{mockComposer} % composer {%cmd} {%argv}',
      ['%cmd' => $name, '%argv' => '-q']
    ]];
    $this->assertSame(0, $status);
    $this->verifyMessages($expect, $jorge->messages, TRUE);
  }

  public function testExecWithInvalidArgv(): void {
    $jorge = new MockJorge(getcwd());
    $jorge->getOutput()->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    $jorge->configure();
    $tool = new MockComposerTool();
    $jorge->addTool($tool);
    $tool->mockComposerApplication($this);
    $jorge->messages = [];

    $tool->mockExec(NULL);
    $expect = [[
      LogLevel::NOTICE,
      '{mockComposer} % composer {%cmd} {%argv}',
      ['%cmd' => '', '%argv' => '']
    ]];
    $this->verifyMessages($expect, $jorge->messages);
    $jorge->messages = [];

    $text = $this->makeRandomString();
    $tool->mockExec($text);
    $expect = [[
      LogLevel::NOTICE,
      '{mockComposer} % composer {%cmd} {%argv}',
      ['%cmd' => '', '%argv' => '']
    ]];
    $this->verifyMessages($expect, $jorge->messages);
    $jorge->messages = [];
  }

  public function testApplyVerbosity(): void {
    $jorge = new MockJorge(getcwd());
    $jorge->configure();
    $tool = new MockComposerTool();
    $jorge->addTool($tool);
    $tool->mockComposerApplication($this);

    $verbosityMap = [
      OutputInterface::VERBOSITY_QUIET        => '-q',
      OutputInterface::VERBOSITY_NORMAL       => '',
      OutputInterface::VERBOSITY_VERBOSE      => '-v',
      OutputInterface::VERBOSITY_VERY_VERBOSE => '-vv',
      OutputInterface::VERBOSITY_DEBUG        => '-vvv',
    ];

    # Test all the known verbosities
    foreach ($verbosityMap as $verbosity => $argv) {
      $jorge->messages = [];
      $tool->setVerbosity($verbosity);
      $command = $this->makeRandomString();
      $tool->runThis(['command' => $command]);
      $expect = [[
        LogLevel::NOTICE,
        '{mockComposer} % composer {%cmd} {%argv}',
        ['%cmd' => $command, '%argv' => $argv]
      ]];
      if ($verbosity != OutputInterface::VERBOSITY_QUIET) {
        $expect[] = ['writeln', ''];
      }
      $this->verifyMessages($expect, $jorge->messages, TRUE);
    }
  }

  public function testArgvJoin(): void {
    $jorge = new MockJorge(getcwd());
    $jorge->getOutput()->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    $jorge->configure();
    $tool = new MockComposerTool();
    $jorge->addTool($tool);
    $tool->mockComposerApplication($this);
    $jorge->messages = [];

    # Make sure it works for truthy things.
    $command = $this->makeRandomString();
    $argv = [
      'command'   => $command,
      'trueThing' => TRUE,
      'argument'  => 'value',
    ];
    $status = $tool->runThis($argv);
    $expect = [[
      LogLevel::NOTICE,
      '{mockComposer} % composer {%cmd} {%argv}',
      ['%cmd' => $command, '%argv' => 'trueThing argument=value -q']
    ]];
    $this->assertSame(0, $status);
    $this->verifyMessages($expect, $jorge->messages, TRUE);
    $jorge->messages = [];

    # Make sure it works for falsy things.
    $command = $this->makeRandomString();
    $argv = [
      'command'    => $command,
      'falseThing' => FALSE,
      'fakeFalse'  => 'false',
      'zeroValue'  => 0,
      'nullValue'  => NULL,
    ];
    $status = $tool->runThis($argv);
    $expect = [[
      LogLevel::NOTICE,
      '{mockComposer} % composer {%cmd} {%argv}',
      ['%cmd' => $command, '%argv' => 'fakeFalse=false zeroValue=0 nullValue -q']
    ]];
    $this->assertSame(0, $status);
    $this->verifyMessages($expect, $jorge->messages, TRUE);
  }

  public function testConfigure(): void {
    $tool = new ComposerTool();
    $this->assertSame('composer', $tool->getName());
  }

}
