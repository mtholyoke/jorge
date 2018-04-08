<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Tool;

use MountHolyoke\Jorge\Tool\ComposerTool;
use MountHolyoke\JorgeTests\Mock\MockComposerTool;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test the functionality of Tool that isn’t covered elsewhere.
 */
final class ComposerToolTest extends TestCase {
  use OutputVerifierTrait;
  use RandomStringTrait;

  public function testApplyVerbosity(): void {
    $tool = new MockComposerTool();

    $verbosityMap = [
      OutputInterface::VERBOSITY_QUIET        => '-q',
      OutputInterface::VERBOSITY_NORMAL       => '',
      OutputInterface::VERBOSITY_VERBOSE      => '-v',
      OutputInterface::VERBOSITY_VERY_VERBOSE => '-vv',
      OutputInterface::VERBOSITY_DEBUG        => '-vvv',
      -1                                      => '',
    ];

    # Test all the known verbosities, plus unknown “-1”.
    foreach ($verbosityMap as $verbosity => $flag) {
      $tool->messages = [];
      $tool->setVerbosity($verbosity);
      $command = $this->makeRandomString();
      $expect  = ['command' => $command];
      if (!empty($flag)) {
        $expect[$flag] = TRUE;
      }

      # Verify the resulting array has a verbosity flag if needed.
      $result = $tool->applyVerbosity(['command' => $command]);
      $this->assertSame($expect, $result);
    }
  }

  public function testArgvJoin(): void {
    $tool = new MockComposerTool();

    # Make sure it works for truthy things.
    $command = $this->makeRandomString();
    $argv = [
      'command'   => $command,
      'trueThing' => TRUE,
      'argument'  => 'value',
    ];
    $expect = 'trueThing argument=value';
    $this->assertSame($expect, $tool->argvJoin($argv));

    # Make sure it works for falsy things.
    $command = $this->makeRandomString();
    $argv = [
      'command'    => $command,
      'falseThing' => FALSE,
      'fakeFalse'  => 'false',
      'zeroValue'  => 0,
      'nullValue'  => NULL,
    ];
    $expect = 'fakeFalse=false zeroValue=0 nullValue';
    $this->assertSame($expect, $tool->argvJoin($argv));
  }

  public function testConfigure(): void {
    $tool = new ComposerTool();
    $this->assertSame('composer', $tool->getName());
  }

  public function testExec(): void {
    $tool = new MockComposerTool();
    $tool->mockComposerApplication($this);

    # Make sure an empty run succeeds.
    $expect = [
      [LogLevel::NOTICE, '{mockComposer} % composer {%cmd} {%argv}', ['%cmd' => '', '%argv' => '']],
      ['writeln',        ''],
    ];
    $this->assertSame(0, $tool->runThis());
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $tool->messages = [];

    # Make sure a command run also succeeds.
    $command = $this->makeRandomString();
    $expect  = [
      [LogLevel::NOTICE, '{mockComposer} % composer {%cmd} {%argv}', ['%cmd' => $command, '%argv' => '']],
      ['writeln',        ''],
    ];
    $this->assertSame(0, $tool->runThis(['command' => $command]));
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $tool->messages = [];
  }

  public function testExecWithInvalidArgv(): void {
    $tool = new MockComposerTool();
    $tool->mockComposerApplication($this);

    # These conditions share the same expect:
    $expect = [
      [LogLevel::NOTICE, '{mockComposer} % composer {%cmd} {%argv}', ['%cmd' => '', '%argv' => '']]
    ];

    # Make sure a NULL is trapped appropriately.
    $exec = $tool->exec(NULL);
    $this->assertSame(0, $exec['status']);
    $this->verifyMessages($expect, $tool->messages);
    $tool->messages = [];

    # Make sure a non-array for $argv is trapped appropriately.
    $random = $this->makeRandomString();
    $exec = $tool->exec($random);
    $this->assertSame(0, $exec['status']);
    $this->verifyMessages($expect, $tool->messages);
    $tool->messages = [];
  }
}
