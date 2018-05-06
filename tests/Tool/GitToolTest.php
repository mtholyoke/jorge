<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Tool;

use MountHolyoke\Jorge\Tool\GitTool;
use MountHolyoke\JorgeTests\Mock\MockGitTool;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
use MountHolyoke\JorgeTests\Tool\ToolInitTestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test the functionality of Tool that isn’t covered elsewhere.
 */
final class GitToolTest extends TestCase {
  use OutputVerifierTrait;
  use RandomStringTrait;
  use ToolInitTestTrait;

  /**
   * Make sure verbosity is being correctly applied to git commands.
   *
   * Git’s various commands have different verbosity flags, so they are
   * assembled within GitTool as arrays rather than strings, and the
   * applyVerbosity() method, called by runThis(), is what makes them
   * into strings for exec().
   *
   * @todo Do this without assuming a Unix-like environment for testing?
   */
  public function testApplyVerbosity(): void {
    $verbosityMap = [
      OutputInterface::VERBOSITY_QUIET => [
        'checkout' => '-q 2>&1',
        'pull'     => '2>&1',
        'status'   => '2>&1',
        '#default' => '2>&1',
      ],
      OutputInterface::VERBOSITY_NORMAL => [
        'checkout' => '',
        'pull'     => '',
        'status'   => '',
        '#default' => '',
      ],
      OutputInterface::VERBOSITY_VERBOSE => [
        'checkout' => '',
        'pull'     => '-v',
        'status'   => '-v',
        '#default' => '-v',
      ],
      OutputInterface::VERBOSITY_VERY_VERBOSE => [
        'checkout' => '',
        'pull'     => '-v',
        'status'   => '-vv',
        '#default' => '-v',
      ],
      OutputInterface::VERBOSITY_DEBUG => [
        'checkout' => '',
        'pull'     => '-v',
        'status'   => '-vv',
        '#default' => '-v',
      ],
    ];

    # List of commands to test, with whether it takes an argument.
    # $bogusCmd validates defaults in verbosity map.
    $bogusCmd = $this->makeRandomString();
    $commands = [
      'checkout' => TRUE,
      'pull'     => FALSE,
      'status'   => FALSE,
      $bogusCmd  => (rand(0, 1) == 1),
    ];

    $tool = new MockGitTool();

    # Make sure that an invalid $argv is returned unchanged.
    $text = $this->makeRandomString();
    $this->assertSame($text, $tool->applyVerbosity($text));

    # Make sure than an empty $argv array is returned as an empty string.
    $this->assertSame('', $tool->applyVerbosity([]));

    # Now test every combination of command and verbosity.
    foreach ($verbosityMap as $verbosity => $map) {
      $tool->setVerbosity($verbosity);

      foreach ($commands as $command => $argument) {
        # Set up the command to run.
        $argv = [$command];
        if ($argument) {
          $argv[] = $this->makeRandomString();
        }

        # Establish expected values.
        $flag = array_key_exists($command, $map) ? $map[$command] : $map['#default'];
        $expect = trim(implode(' ', array_merge($argv, [$flag])));

        # Make sure we got what we expected.
        $this->assertSame($expect, $tool->applyVerbosity($argv));
      }
    }
  }

  public function testConfigure(): void {
    $tool = new GitTool();
    $this->assertSame('git', $tool->getName());
    $this->assertFalse($tool->getStatus()->clean);
  }

  public function testInitialize(): void {
    $messages = $this->runAllToolInitTests('git');
    $mockName = '{mockGit} ';
    $expect = [
      # checkInitWithBadExecutable
      [LogLevel::ERROR,  $mockName . 'Cannot set executable "{%executable}"'],
      # tool->setExecutable
      [LogLevel::DEBUG,  $mockName . 'Executable is "{%executable}"'],
      # checkInitWithoutConfig (2nd time)
      [LogLevel::NOTICE, $mockName . '$ {%command}'],
      # checkInitWithValidConfig
      [LogLevel::NOTICE, $mockName . '$ {%command}'],
    ];
    $this->verifyMessages($expect, $messages);
  }

  // TODO: Test with empty .git folder?

  public function testInitializeWithDirtyRepo(): void {
    # Set up a dirty repo.
    $tempDir = (new TemporaryDirectory())->create();
    $root = realpath($tempDir->path());
    chdir($root);
    exec('git init .');
    touch($this->makeRandomString());

    $tool = new MockGitTool();
    $tool->stubJorge['getPath'] = $root;
    $tool->setExecutable('git');
    $tool->initialize();
    $this->assertTrue($tool->isEnabled());
    $this->assertFalse($tool->getStatus()->clean);

    $tempDir->delete();
  }

  /**
   * @todo Do this without assuming the OS will provide `echo`?
   */
  public function testUpdateStatus(): void {
    $tool = new MockGitTool();
    $tool->setExecutable('echo');

    # We expect FALSE because the executable (`echo status`) does not
    # produce a line containing the string 'nothing to commit'.
    $tool->updateStatus();
    $this->assertFalse($tool->getStatus()->clean);

    # Run a test that should produce a clean status.
    $clean = [
      'On branch master',
      'Your branch is up to date with "origin/master".',
      '',
      'nothing to commit, working tree clean',
    ];
    $tool->updateStatus($clean);
    $this->assertTrue($tool->getStatus()->clean);
  }
}
