<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Tool;

use MountHolyoke\Jorge\Jorge;
use MountHolyoke\Jorge\Tool\Tool;
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
    $tool = new Tool($name);
    $this->assertSame($name, $tool->getName());
  }

  public function testDisableEnable(): void {
    $tool = $this->mock;
    $tool->enable();
    $this->assertTrue($tool->isEnabled());
    $tool->disable();
    $this->assertFalse($tool->isEnabled());
  }

  public function testGetConfig(): void {
    $tool = $this->mock;

    # Nothing set, no key, no default.
    $this->assertNull($tool->getConfig());

    # Nothing set, no key.
    $default = $this->makeRandomString();
    $this->assertSame($default, $tool->getConfig(NULL, $default));

    # Nothing set, no default.
    $key = $this->makeRandomString();
    $this->assertNull($tool->getConfig($key));

    # Nothing set.
    $key = $this->makeRandomString();
    $default = $this->makeRandomString();
    $this->assertSame($default, $tool->getConfig($key, $default));

    # Set some configuration!
    $key = $this->makeRandomString();
    $val = $this->makeRandomString();
    $config = [$key => $val];
    $tool->setConfig($config);

    # Make a key not already in use:
    do {
      $bad = $this->makeRandomString();
    } while ($bad == $key);

    # No key, no default.
    $this->assertSame($config, $tool->getConfig());

    # No key => should be $config, not $default.
    $default = $this->makeRandomString();
    $this->assertSame($config, $tool->getConfig(NULL, $default));

    # Good key, no default.
    $this->assertSame($val, $tool->getConfig($key));

    # Good key => should be $val, not $default.
    $default = $this->makeRandomString();
    $this->assertSame($val, $tool->getConfig($key, $default));

    # Bad key, no default.
    $this->assertNull($tool->getConfig($bad));

    # Bad key => should be $default.
    $default = $this->makeRandomString();
    $this->assertSame($default, $tool->getConfig($bad, $default));
  }

  public function testGetStatus(): void {
    $tool = $this->mock;
    $this->assertFalse($tool->getStatus());
    $text = $this->makeRandomString();
    $tool->setStatus($text);
    $this->assertSame($text, $tool->getStatus());
  }

  /**
   * @todo Do this without assuming the OS will provide `echo`?
   */
  public function testOtherGetters(): void {
    $tool  = $this->mock;
    $exec  = 'echo';
    $jorge = new Jorge();
    $regex = "/$exec$/";
    $tool->setApplication($jorge, $exec);
    $this->assertSame($jorge, $tool->getApplication());
    $this->assertRegExp($regex, $tool->getExecutable());
  }

  /**
   * @todo Do this without assuming the OS will provide `echo`?
   */
  public function testRun(): void {
    $tool = $this->mock;
    $name = $tool->getName();

    # Make sure run() fails if not enabled.
    $this->assertFalse($tool->isEnabled());
    $tool->run();
    $expectDisabled = [
      [LogLevel::ERROR, '{' . $name . '} Tool not enabled', []],
    ];
    $this->verifyMessages($expectDisabled, $tool->messages, TRUE);
    $tool->messages = [];

    # Give it an executable and save what it finds.
    $tool->setExecutable('echo');
    $echo = $tool->getExecutable();
    $expect = [
      [LogLevel::DEBUG, '{' . $name . '} Executable is "{%executable}"', ['%executable' => $echo]],
    ];
    $this->verifyMessages($expect, $tool->messages);
    $tool->messages = [];

    # Make sure it’s still disabled and doesn’t run.
    $this->assertFalse($tool->isEnabled());
    $tool->run();
    $this->verifyMessages($expectDisabled, $tool->messages, TRUE);
    $tool->messages = [];

    # Add a thing to echo and make sure it still doesn't run.
    $this->assertFalse($tool->isEnabled());
    $text = $this->makeRandomString();
    $tool->run($text);
    $this->verifyMessages($expectDisabled, $tool->messages, TRUE);
    $tool->messages = [];

    # Enable it and make sure it runs now.
    $tool->enable();
    $this->assertTrue($tool->isEnabled());
    $this->assertSame(0, $tool->run($text));
    $expect = [
      [LogLevel::NOTICE, '{' . $name . '} $ {%command}', ['%command' => "$echo $text"]],
      ['writeln',        $text],
    ];
    $this->verifyMessages($expect, $tool->messages, TRUE);
  }

  /**
   * @todo Do this without assuming the OS will provide `echo`?
   */
  public function testRunThis(): void {
    $tool = $this->mock;
    $name = $tool->getName();

    # Make sure runThis() triggers exec()’s failure without an executable.
    $this->assertSame(1, $tool->runThis());
    $expect = [
      [LogLevel::ERROR, '{' . $name . '} Cannot execute a blank command', []],
    ];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $tool->messages = [];

    # Give it an executable and save what it finds.
    $tool->setExecutable('echo');
    $echo = $tool->getExecutable();
    $tool->messages = [];

    # Make sure it runs without being enabled.
    $this->assertFalse($tool->isEnabled());
    $text = $this->makeRandomString();
    $this->assertSame(0, $tool->runThis($text));
    $expect = [
      [LogLevel::NOTICE, '{' . $name . '} $ {%command}', ['%command' => "$echo $text"]],
      ['writeln',        $text],
    ];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $tool->messages = [];

    # Make sure it runs but doesn’t output when verbosity is quiet.
    array_pop($expect);
    $tool->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    $this->assertSame(0, $tool->runThis($text));
    $this->verifyMessages($expect, $tool->messages, TRUE);
  }

  public function testSetExecutable(): void {
    $tool = $this->mock;
    $name = $tool->getName();

    $exec = $this->makeRandomString();
    $tool->setExecutable($exec);
    $expect = [
      [LogLevel::ERROR, '{' . $name . '} Cannot set executable "{%executable}"', ['%executable' => $exec]],
    ];
    $this->verifyMessages($expect, $tool->messages, TRUE);
  }
}
