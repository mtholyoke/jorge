<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests;

use MountHolyoke\JorgeTests\MockConsoleOutput;
use MountHolyoke\JorgeTests\MockJorge;
use MountHolyoke\Jorge\Tool\Tool;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Output\OutputInterface;

final class JorgeTest extends TestCase {
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
    $this->assertSame(count($startup), count($this->jorge->messages));
    foreach ($startup as $expected) {
      $actual = array_shift($this->jorge->messages);
      # Paths may be different on different computers
      array_pop($actual);
      $this->assertSame($expected, $actual);
    }
    $this->assertSame(0, count($this->jorge->messages));

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
    // $this->assertSame('jorge', $this->jorge->getConfig('appType'));

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
    foreach ($logLevels as $logLevel) {
      $logString = bin2hex(random_bytes(4));
      $logExpect = [$logLevel, "$logString", []];
      $this->jorge->log($logLevel, "$logString");
      $this->assertSame($logExpect, end($this->jorge->messages));
    }

    # Verify that writeln works as expected:
    $wlnString = bin2hex(random_bytes(4));
    $wlnExpect = ['writeln', "$wlnString"];
    $this->jorge->getOutput()->writeln("$wlnString");
    $this->assertSame($wlnExpect, end($this->jorge->messages));
  }

  public function testAddToolGetTool(): void {
    $initialTools = $this->jorge->allTools();
    # Find a name we don’t have and verify that getTool() responds correctly.
    do {
      $toolName = bin2hex(random_bytes(4));
    } while (array_key_exists($toolName, $this->jorge->allTools()));
    $this->assertNull($this->jorge->getTool($toolName));
    $expectLog = [LogLevel::WARNING, 'Can’t get tool "{%tool}"', ['%tool' => $toolName]];
    $this->assertSame($expectLog, end($this->jorge->messages));

    # Add a tool with that name and verify that getTool() responds correctly
    # and that we added exactly one tool.
    $toolInstance = new Tool($toolName);
    $this->jorge->addTool($toolInstance, 'echo');
    $this->assertArrayHasKey($toolName, $this->jorge->allTools());
    $this->assertSame($toolInstance, $this->jorge->getTool($toolName));
    $this->assertSame(count(array_keys($initialTools)) + 1, count(array_keys($this->jorge->allTools())));

    # Add another tool with that name and verify that we get an exception.
    $this->expectException(LogicException::class);
    $this->jorge->addTool(new Tool($toolName), 'ls');

    # This should echo the tool name without checking enablement.
    $toolInstance->runThis($toolName);
    $this->assertSame(['writeln', $toolName], end($this->jorge->messages));
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
