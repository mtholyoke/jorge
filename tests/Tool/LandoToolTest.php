<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Tool;

use MountHolyoke\Jorge\Tool\LandoTool;
use MountHolyoke\JorgeTests\Mock\MockJorge;
use MountHolyoke\JorgeTests\Mock\MockLandoTool;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Spatie\TemporaryDirectory\TemporaryDirectory;
// use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test the functionality of Tool that isn’t covered elsewhere.
 */
final class LandoToolTest extends TestCase {
  use OutputVerifierTrait;
  use RandomStringTrait;

  public function testConfigure(): void {
    $tool = new LandoTool();
    $this->assertSame('lando', $tool->getName());
  }

  public function testRequireStarted(): void {
    $tempDir = (new TemporaryDirectory())->create();
    $root = realpath($tempDir->path());
    mkdir($root . DIRECTORY_SEPARATOR . '.jorge');
    chdir($root);
    $project = $this->makeRandomString();
    $config  = "name: $project\n";
    file_put_contents('.lando.yml', $config);
    $jorge = new MockJorge($root);
    $jorge->configure();
    $tool = new MockLandoTool($project);
    $jorge->addTool($tool, 'echo');
    $exec = $tool->getExecutable();
    $jorge->messages = [];

    # Make sure the tool is ready, then call the method.
    $this->assertTrue($tool->isEnabled());
    $tool->requireStarted();

    # Make sure it executed the things and the status is now running.
    $expect = [
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$exec list"]],
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$exec start"]],
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$exec list"]],
    ];
    $this->verifyMessages($expect, $jorge->messages, TRUE);
    $this->assertTrue($tool->getStatus()->running);
    $jorge->messages = [];

    # Now that it’s running, make sure requiredStarted() doesn’t try to start it.
    $tool->requireStarted();
    $expect = [
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$exec list"]],
    ];
    $this->verifyMessages($expect, $jorge->messages, TRUE);
    $this->assertTrue($tool->getStatus()->running);

    $tempDir->delete();
  }

  public function testUpdateStatus(): void {
    $tempDir = (new TemporaryDirectory())->create();
    $root = realpath($tempDir->path());
    mkdir($root . DIRECTORY_SEPARATOR . '.jorge');
    chdir($root);
    $project = $this->makeRandomString();
    $config  = "name: $project\n";
    file_put_contents('.lando.yml', $config);
    $jorge = new MockJorge($root);
    $jorge->configure();
    $tool = new MockLandoTool($project);
    $jorge->addTool($tool, 'echo');
    $exec = $tool->getExecutable();
    $jorge->messages = [];

    # Make sure it fails if `lando list` has nonzero exit code.
    $tool->updateStatus();
    $expect = [
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$exec list"]],
      [LogLevel::ERROR,  '{mockLando} Unable to determine status', []],
    ];
    $this->verifyMessages($expect, $jorge->messages, TRUE);
    $jorge->messages = [];

    # Make sure it fails if the tool is disabled.
    $tool->disable();
    $tool->updateStatus();
    $expect = [
      [LogLevel::NOTICE,  '{mockLando} $ {%command}', ['%command' => "$exec list"]],
      [LogLevel::WARNING, '{mockLando} No Lando environment configured or specified', []],
    ];
    $this->verifyMessages($expect, $jorge->messages, TRUE);
    $jorge->messages = [];

    # Make sure it fails if it can’t find this project’s name.
    $tool->enable();
    do {
      $unknown = $this->makeRandomString();
    } while ($unknown == $project);
    $tool->updateStatus($unknown);
    $expect = [
      [LogLevel::NOTICE,  '{mockLando} $ {%command}', ['%command' => "$exec list"]],
      [LogLevel::WARNING, '{mockLando} Unable to determine status for Lando environment "{%name}"', ['%name' => $unknown]],
    ];
    $this->verifyMessages($expect, $jorge->messages, TRUE);

    $tempDir->delete();
  }

  public function testParseLandoList() {
    $tool = new MockLandoTool();
    $text = $this->makeRandomString();
    $key  = $this->makeRandomString();
    $val  = $this->makeRandomString();

    $keyval = (object) [$key => $val];
    $valkey = (object) [$val => $key];

    # Make sure we can skip over version complaints and other preamble text.
    $lines = [
      $text,
      '',
      '{',
      '"' . $key . '": "' . $val .'"',
      '}',
    ];
    $this->assertEquals($keyval, $tool->parseLandoList($lines)[0]);

    # Make sure we can parse output from lando 3.0.0 >= beta.37
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
  }
}
