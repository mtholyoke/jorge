<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Tool;

use MountHolyoke\Jorge\Jorge;
use MountHolyoke\Jorge\Tool\Tool;
use MountHolyoke\JorgeTests\Mock\MockJorge;
use MountHolyoke\JorgeTests\Mock\MockTool;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test the functionality of Tool that isn’t covered elsewhere.
 */
final class ToolTest extends TestCase {
  use OutputVerifierTrait;
  use RandomStringTrait;

  protected $mock;

  protected function setUp(): void {
    $name = $this->makeRandomString();
    $this->mock = new MockTool($name);
  }

  protected function tearDown(): void {
    unset($this->mock);
  }

  public function test__Construct(): void {
    $this->expectException(LogicException::class);
    $tool = new Tool();

    $name = $this->makeRandomString();
    $tool = new Tool("x$name");
    $this->assertSame("x$name", $tool->getName());
  }

  public function testEnableDisable(): void {
    # This uses a MockTool for its setStatus() override.
    $tool = $this->mock;
    $tool->setStatus(TRUE);
    $this->assertTrue($tool->isEnabled());
    $tool->setStatus(FALSE);
    $this->assertFalse($tool->isEnabled());
  }

  /**
   * @todo Do this without assuming a Unix-like environment for testing?
   */
  public function testGetters(): void {
    $tool  = $this->mock;
    $exec  = 'echo';
    $jorge = new Jorge();
    $regex = "/$exec$/";
    $tool->setApplication($jorge, $exec);
    $this->assertSame($jorge, $tool->getApplication());
    $this->assertRegExp($regex, $tool->getExecutable());
  }

  public function testGetStatus(): void {
    $tool = $this->mock;
    $this->assertFalse($tool->getStatus());
    $text = $this->makeRandomString();
    $tool->setStatus($text);
    $this->assertSame($text, $tool->getStatus());
  }

  /**
   * @todo Do this without assuming a Unix-like environment for testing?
   */
  public function testRun(): void {
    $tool = $this->mock;

    # Make sure run() fails if not enabled.
    $this->assertFalse($tool->isEnabled());
    $tool->run();
    $expect = [[LogLevel::ERROR, 'Tool not enabled', []]];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $tool->messages = [];

    # Give it an executable and save what it finds.
    $jorge = new MockJorge(getcwd());
    $tool->setApplication($jorge, 'echo');
    $this->assertSame(1, count($tool->messages));
    $executable = $tool->messages[0][2]['%executable'];
    $this->assertSame([], $jorge->messages);
    $tool->messages = [];

    # Make sure it’s still disabled and doesn’t run.
    $this->assertFalse($tool->isEnabled());
    $tool->run();
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $this->assertSame([], $jorge->messages);
    $tool->messages = [];

    # Add a thing to echo and make sure it still doesn't run.
    $this->assertFalse($tool->isEnabled());
    $text = $this->makeRandomString();
    $tool->run($text);
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $this->assertSame([], $jorge->messages);
    $tool->messages = [];

    # Enable it and make sure it runs now.
    $tool->setStatus(TRUE);
    $this->assertTrue($tool->isEnabled());
    $result = $tool->run($text);
    $this->assertSame(0, $result);
    $expect = [[
      LogLevel::NOTICE,
      '$ {%command}',
      ['%command' => "$executable $text"]
    ]];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $this->verifyMessages([['writeln', $text]], $jorge->messages);
  }

  /**
   * @todo Do this without assuming a Unix-like environment for testing?
   */
  public function testRunThis(): void {
    $tool = $this->mock;

    # Make sure runThis() triggers exec()’s failure without an executable.
    $result = $tool->runThis();
    $this->assertSame(1, $result);
    $expect = [[LogLevel::ERROR, 'Cannot execute a blank command', []]];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $tool->messages = [];

    # Give it an executable and save what it finds.
    $jorge = new MockJorge(getcwd());
    $tool->setApplication($jorge, 'echo');
    $this->assertSame(1, count($tool->messages));
    $executable = $tool->messages[0][2]['%executable'];
    $this->assertSame([], $jorge->messages);
    $tool->messages = [];

    # Make sure it runs without being enabled.
    $this->assertFalse($tool->isEnabled());
    $text = $this->makeRandomString();
    $result = $tool->runThis($text);
    $this->assertSame(0, $result);
    $expect = [[
      LogLevel::NOTICE,
      '$ {%command}',
      ['%command' => "$executable $text"]
    ]];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $this->verifyMessages([['writeln', $text]], $jorge->messages);
    $tool->messages = [];
    $jorge->messages = [];

    # Make sure it runs but doesn’t output when verbosity is quiet.
    $tool->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    $result = $tool->runThis($text);
    $this->assertSame(0, $result);
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $this->assertSame([], $jorge->messages);
  }

  public function testSetExecutable(): void {
    # This uses MockTool to call protected setExecutable() and capture output.
    $tool = $this->mock;
    $exec = $this->makeRandomString();
    $tool->setApplication(new Jorge(), $exec);
    $expect = [[
      LogLevel::ERROR,
      'Cannot set executable "{%executable}"',
      ['%executable' => $exec]
    ]];
    $this->verifyMessages($expect, $tool->messages, TRUE);
  }
}
