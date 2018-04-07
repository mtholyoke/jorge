<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests;

use MountHolyoke\Jorge\Tool\Tool;
use MountHolyoke\JorgeTests\Mock\MockConsoleOutput;
use MountHolyoke\JorgeTests\Mock\MockJorge;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Output\OutputInterface;

final class JorgeTest extends TestCase {
  use OutputVerifierTrait;
  use RandomStringTrait;

  protected $jorge;
  protected $tempDir;

  /**
   * Creates a Jorge-like object on which we can test output.
   */
  protected function setUp(): void {
    $this->tempDir = (new TemporaryDirectory())->create();
    $root = $this->tempDir->path();
    mkdir($root . DIRECTORY_SEPARATOR . '.jorge');
    chdir($root);
    touch('.jorge' . DIRECTORY_SEPARATOR . 'config.yml');
    $this->jorge = new MockJorge($root);
    $this->jorge->configure();
  }

  protected function tearDown(): void {
    $this->jorge = NULL;
    $this->tempDir->delete();
  }

  /**
   * Verify the MockJorge is working as expected: that it starts the same as
   * a typical Jorge, but with output functions replaced.
   */
  public function testMockJorge() {
    # Startup messages (all verbosities):
    $startup = [
      [LogLevel::NOTICE, 'Project root: {%root}'],
      [LogLevel::DEBUG,  '{composer} Executable is "{%executable}"'],
      [LogLevel::DEBUG,  '{git} Executable is "{%executable}"'],
      [LogLevel::NOTICE, '{git} $ {%command}'],
      [LogLevel::DEBUG,  '{lando} Executable is "{%executable}"'],
      ['NULL',           'Can’t read config file {%filename}'],
    ];
    $this->verifyMessages($startup, $this->jorge->messages);
    $this->jorge->messages = [];

    # From __construct():
    $this->assertInstanceOf(MockJorge::class, $this->jorge);
    $output = $this->jorge->getOutput();
    $this->assertInstanceOf(MockConsoleOutput::class, $output);
    $this->assertTrue($output->isDecorated());
    $this->assertSame(OutputInterface::VERBOSITY_NORMAL, $output->getVerbosity());
    $this->assertSame(0, $_ENV['SHELL_VERBOSITY']);

    # From configure():
    $this->assertSame('Jorge', $this->jorge->getName());
    $this->assertRegExp('/\d\.\d\.\d/', $this->jorge->getVersion());
    $this->assertSame(realpath($this->tempDir->path()), $this->jorge->getPath());
    $this->assertSame([], $this->jorge->getConfig());

    # Verify that MockJorge’s log() works as expected:
    $logLevels = [
      LogLevel::EMERGENCY,
      LogLevel::ALERT,
      LogLevel::CRITICAL,
      LogLevel::ERROR,
      LogLevel::WARNING,
      LogLevel::NOTICE,
      LogLevel::INFO,
      LogLevel::DEBUG,
    ];
    $expect = [];
    foreach ($logLevels as $level) {
      $text = $this->makeRandomString();
      $expect[] = [$level, $text, []];
      $this->jorge->log($level, $text);
    }
    $this->verifyMessages($expect, $this->jorge->messages, TRUE);
    $this->jorge->messages = [];

    # Verify that writeln works as expected:
    $text = $this->makeRandomString();
    $this->jorge->getOutput()->writeln($text);
    $this->verifyMessages([['writeln', $text]], $this->jorge->messages);
  }

  /**
   * @todo Do this without assuming a Unix-like environment for testing?
   */
  public function testAddToolGetTool(): void {
    $this->jorge->messages = [];
    $initialTools = $this->jorge->allTools();
    # Find a name we don’t have and verify that getTool() responds correctly.
    do {
      $name = $this->makeRandomString();
    } while (array_key_exists($name, $initialTools));
    $this->assertNull($this->jorge->getTool($name));
    $expect = [
      [LogLevel::WARNING, 'Can’t get tool "{%tool}"', ['%tool' => $name]]
    ];
    $this->verifyMessages($expect, $this->jorge->messages, TRUE);
    $this->jorge->messages = [];

    # Add a tool with that name
    $tool = new Tool($name);
    $this->jorge->addTool($tool, 'echo');
    $expect = [
      [LogLevel::DEBUG, '{' . $name . '} Executable is "{%executable}"']
    ];
    $this->verifyMessages($expect, $this->jorge->messages);
    $this->jorge->messages = [];

    # Verify that getTool() responds correctly and that we added exactly one tool.
    $currentTools = $this->jorge->allTools();
    $this->assertArrayHasKey($name, $currentTools);
    $this->assertSame($tool, $this->jorge->getTool($name));
    $this->assertSame(count(array_keys($initialTools)) + 1, count(array_keys($currentTools)));

    # Add another tool with that name and verify that we get an exception.
    $this->expectException(LogicException::class);
    $this->jorge->addTool(new Tool($name), 'ls');
    $this->verifyMessages($expect, $this->jorge->messages);
    $this->jorge->messages = [];

    # This should echo the text without checking enablement.
    $text = $this->makeRandomString();
    $tool->runThis($text);
    $expect = [
      [LogLevel::NOTICE, '{' . $name . '} $ {%command}'],
      ['writeln',        $text                         ],
    ];
    $this->verifyMessages($expect, $this->jorge->messages);
  }

  /**
   * @todo How do we actually verify this is the normal start?
   */
  public function testRun(): void {
    $this->jorge->messages = [];
    $this->jorge->run();

    # Number of lines of output is predictable.
    $numLines = 22 + (2 * count($this->jorge->all()));
    $this->assertSame($numLines, count($this->jorge->messages));

    # None of the lines should indicate an error.
    foreach ($this->jorge->messages as $message) {
      $this->assertNotRegExp('/<(warning|error|critical|alert|emergency)>/', $message[1]);
    }
  }
}
