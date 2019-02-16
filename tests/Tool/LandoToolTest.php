<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Tool;

use MountHolyoke\Jorge\Tool\LandoTool;
use MountHolyoke\JorgeTests\Mock\MockLandoTool;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
use MountHolyoke\JorgeTests\Tool\ToolInitTestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test the functionality of Tool that isn’t covered elsewhere.
 */
final class LandoToolTest extends TestCase {
  use OutputVerifierTrait;
  use RandomStringTrait;
  use ToolInitTestTrait;

  public function testApplyVerbosity(): void {
    $verbosityMap = [
      OutputInterface::VERBOSITY_QUIET        => '2>&1',
      OutputInterface::VERBOSITY_NORMAL       => '',
      OutputInterface::VERBOSITY_VERBOSE      => '-- -v',
      OutputInterface::VERBOSITY_VERY_VERBOSE => '-- -vv',
      OutputInterface::VERBOSITY_DEBUG        => '-- -vvvv',
      0                                       => '',
    ];

    $tool = new MockLandoTool();
    foreach ($verbosityMap as $verbosity => $flag) {
      $argc = rand(0, 1);
      $text = $this->makeRandomString();
      $tool->setVerbosity($verbosity);
      $response = $tool->applyVerbosity($argc ? $text : '');
      if ($argc == 0) {
        $this->assertSame($flag, $response);
      } elseif (empty($flag)) {
        $this->assertSame($text, $response);
      } else {
        $this->assertSame("$text $flag", $response);
      }
    }
  }

  public function testConfigure(): void {
    $tool = new LandoTool();
    $this->assertSame('lando', $tool->getName());
  }

  public function testInitialize(): void {
    $messages = $this->runAllToolInitTests('lando');
    $mockName = '{mockLando} ';
    $expect = [
      # checkInitWithBadExecutable
      [LogLevel::ERROR, $mockName . 'Cannot set executable "{%executable}"'],
      # tool->setExecutable
      [LogLevel::DEBUG, $mockName . 'Executable is "{%executable}"'],
      # checkInitWithoutConfig (2nd time)
      ['NULL',          $mockName . 'Can’t read config file {%filename}'],
    ];
    $this->verifyMessages($expect, $messages);
  }

  public function testParseLandoList() {
    $tool = new MockLandoTool();
    $text = $this->makeRandomString();
    $key  = $this->makeRandomString();
    $val  = $this->makeRandomString();

    $keyval = (object) [$key => $val];
    $valkey = (object) [$val => $key];

    # Make sure we can skip over version complaints and other preamble text.
    $tool->setVersion('v3.0.0-beta.36');
    $lines = [
      $text,
      '',
      '{',
      '"' . $key . '": "' . $val .'"',
      '}',
    ];
    $this->assertEquals($keyval, $tool->parseLandoList($lines)[0]);

    # Make sure we can parse output from lando 3.0.0 <= beta.36
    $lines = [
      '{',
      '"' . $key . '": "' . $val .'"',
      '}',
      '{',
      '"' . $val . '": "' . $key .'"',
      '}',
    ];
    $this->assertEquals($keyval, $tool->parseLandoList($lines)[0]);
    $this->assertEquals($valkey, $tool->parseLandoList($lines)[1]);

    # Make sure we can parse output from 3.0.0-beta.37 to 3.0.0-rc.1
    $tool->setVersion('v3.0.0-beta.48');
    $lines = [
      '[',
      '{',
      '"' . $key . '": "' . $val .'"',
      '},',
      '{',
      '"' . $val . '": "' . $key .'"',
      '}',
      ']',
    ];
    $this->assertEquals($keyval, $tool->parseLandoList($lines)[0]);
    $this->assertEquals($valkey, $tool->parseLandoList($lines)[1]);

    # Make sure we can parse output from 3.0.0 >= rc.2
    $tool->setVersion('v3.0.0-rc.9');
    $lines = [
      '{',
      $text . ': [',
      '{',
      $key . ": '" . $val . "'",
      '},',
      '{',
      $val . ": '" . $key . "'",
      '}',
      ']',
      '}',
    ];
    $this->assertEquals($text, $tool->parseLandoList($lines)[0]->name);
    $this->assertTrue($tool->parseLandoList($lines)[0]->running);
    $this->assertEquals($keyval, $tool->parseLandoList($lines)[0]->info[0]);
    $this->assertEquals($valkey, $tool->parseLandoList($lines)[0]->info[1]);
  }

  /**
   * @todo Do this without assuming the OS will provide `echo`?
   */
  public function testRequireStarted(): void {
    $project = $this->makeRandomString();
    $tool = new MockLandoTool($project);
    $tool->setConfig(['name' => $project]);
    $tool->setExecutable('echo');
    $echo = $tool->getExecutable();
    $tool->enable();
    $tool->messages = [];

    # Make sure the tool is ready.
    $this->assertTrue($tool->isEnabled());

    # First test should fail: can’t determine version.
    $tool->requireStarted();
    $expect = [
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$echo version"]],
      [LogLevel::ERROR,  '{mockLando} Unable to determine version', []],
    ];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $this->assertFalse($tool->isEnabled());
    $tool->enable();
    $tool->messages = [];

    # Second test should succeed with warning because version is weird.
    $tool->requireStarted();
    $expect = [
      [LogLevel::NOTICE,  '{mockLando} $ {%command}', ['%command' => "$echo version"]],
      [LogLevel::WARNING, '{mockLando} Unrecognized Lando version %v; some functions may not work.', ['%v' => $tool->getVersion()]],
      [LogLevel::NOTICE,  '{mockLando} $ {%command}', ['%command' => "$echo list"]],
      [LogLevel::WARNING, '{mockLando} Unable to determine status for Lando environment "{%name}"', ['%name' => $project]],
      [LogLevel::NOTICE,  '{mockLando} $ {%command}', ['%command' => "$echo start"]],
      [LogLevel::NOTICE,  '{mockLando} $ {%command}', ['%command' => "$echo list"]],
    ];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $this->assertTrue($tool->getStatus()->running);
    $tool->setStatus(NULL);
    $tool->setVersion(NULL);
    $tool->messages = [];

    # Third test should succeed without warning.
    # Make sure it executed the things and the status is now running.
    $tool->requireStarted();
    $expect = [
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$echo version"]],
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$echo list"]],
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$echo start"]],
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$echo list"]],
    ];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $this->assertTrue($tool->getStatus()->running);
    $tool->messages = [];

    # Now that it’s running, make sure requiredStarted() doesn’t try to start it.
    $tool->requireStarted();
    $expect = [
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$echo list"]],
    ];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $this->assertTrue($tool->getStatus()->running);
  }

  /**
   * @todo Do this without assuming the OS will provide `echo`?
   */
  public function testUpdateStatus(): void {
    $project = $this->makeRandomString();
    $tool = new MockLandoTool($project);
    $tool->setConfig(['name' => $project]);
    $tool->setExecutable('echo');
    $echo = $tool->getExecutable();
    $tool->messages = [];

    # Make sure it fails if it can’t find this project’s name.
    do {
      $unknown = $this->makeRandomString();
    } while ($unknown == $project);
    $tool->updateStatus($unknown);
    $expect = [
      [LogLevel::NOTICE,  '{mockLando} $ {%command}', ['%command' => "$echo list"]],
      [LogLevel::WARNING, '{mockLando} Unable to determine status for Lando environment "{%name}"', ['%name' => $unknown]],
    ];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $tool->messages = [];

    # Make sure it fails if the tool is disabled.
    $this->assertFalse($tool->isEnabled());
    $tool->updateStatus();
    $expect = [
      [LogLevel::WARNING, '{mockLando} No Lando environment configured or specified', []],
    ];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $tool->messages = [];

    # Make sure it fails if `lando list` has nonzero exit code.
    $tool->enable();
    $tool->updateStatus();
    $expect = [
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$echo list"]],
      [LogLevel::ERROR,  '{mockLando} Unable to determine status', []],
    ];
    $this->verifyMessages($expect, $tool->messages, TRUE);
    $tool->messages = [];
  }
}
