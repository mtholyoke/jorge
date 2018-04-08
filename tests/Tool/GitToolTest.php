<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Tool;

use MountHolyoke\Jorge\Tool\GitTool;
use MountHolyoke\JorgeTests\Mock\MockJorge;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
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

  public function testApplyVerbosityEdgeCases() {
    $jorge = new MockJorge(getcwd());
    $tool  = new GitTool();
    # Replace the executable so we don’t actually run Git:
    $tool->setApplication($jorge, 'echo');
    $executable = $tool->getExecutable();
    $jorge->messages = [];

    # Make sure that an invalid $argv is returned unchanged.
    $text = $this->makeRandomString();
    $expect = [
      [LogLevel::NOTICE, '{git} $ {%command}', ['%command' => "$executable $text"]],
      ['writeln',        $text                                                    ]
    ];
    $tool->runThis($text);
    $this->verifyMessages($expect, $jorge->messages, TRUE);
    $jorge->messages = [];

    # Make sure than an empty $argv array is returned as an empty string.
    $expect = [
      [LogLevel::NOTICE, '{git} $ {%command}', ['%command' => $executable]],
      ['writeln',        ''                                               ],
    ];
    $tool->runThis([]);
    $this->verifyMessages($expect, $jorge->messages, TRUE);
  }

  public function testConfigure(): void {
    $tool = new GitTool();
    $this->assertSame('git', $tool->getName());
    $this->assertFalse($tool->getStatus()->clean);
  }

  public function testInitializeWithDirtyRepo(): void {
    $tempDir = (new TemporaryDirectory())->create();
    $root = realpath($tempDir->path());
    chdir($root);
    mkdir('.jorge');
    exec('git init .');
    touch($this->makeRandomString());
    $jorge = new MockJorge($root);
    $jorge->configure();
    $tool = $jorge->getTool('git');

    # Verify that the tool is enabled and the status is clean.
    $startup = [
      [LogLevel::NOTICE, 'Project root: {%root}'],
      [LogLevel::ERROR,  'Can’t read config file {%filename}'],
      [LogLevel::DEBUG,  '{composer} Executable is "{%executable}"'],
      ['NULL',           'Can’t read config file {%filename}'],
      [LogLevel::DEBUG,  '{git} Executable is "{%executable}"'],
      [LogLevel::NOTICE, '{git} $ {%command}'],
      [LogLevel::DEBUG,  '{lando} Executable is "{%executable}"'],
      ['NULL',           'Can’t read config file {%filename}'],
    ];
    $this->verifyMessages($startup, $jorge->messages);
    $this->assertTrue($tool->isEnabled());
    $this->assertFalse($tool->getStatus()->clean);
    $tempDir->delete();
  }

  public function testUpdateStatus(): void {
    $jorge = new MockJorge(getcwd());
    $tool  = new GitTool();
    # Replace the executable so we don’t actually run Git:
    $tool->setApplication($jorge, 'echo');

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

    $jorge  = new MockJorge(getcwd());
    $output = $jorge->getOutput();
    $tool   = new GitTool();

    foreach ($verbosityMap as $key => $map) {
      $output->setVerbosity($key);
      # Replace the executable so we don’t actually run Git:
      $tool->setApplication($jorge, 'echo');
      foreach ($commands as $command => $argument) {
        $jorge->messages = [];

        # Set up the command to run.
        $argv = [$command];
        if ($argument) {
          $argv[] = $this->makeRandomString();
        }

        # Establish expected values.
        $flag = array_key_exists($command, $map) ? $map[$command] : $map['#default'];
        $execString = trim(implode(' ', array_merge($argv, [$flag])));
        $expect = [[
          LogLevel::NOTICE,
          '{git} $ {%command}',
          ['%command' => $tool->getExecutable() . ' ' . $execString]
        ]];
        if ($key != OutputInterface::VERBOSITY_QUIET) {
          $expect[] = ['writeln', $execString];
        }

        # Make sure we got what we expected.
        $tool->runThis($argv);
        $this->verifyMessages($expect, $jorge->messages, TRUE);
      }
    }
  }
}
