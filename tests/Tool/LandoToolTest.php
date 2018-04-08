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
    $this->verifyMessages($expect, $jorge->messages);
    $this->assertTrue($tool->getStatus()->running);
    $jorge->messages = [];

    # Now that it’s running, make sure requiredStarted() doesn’t try to start it.
    $tool->requireStarted();
    $expect = [
      [LogLevel::NOTICE, '{mockLando} $ {%command}', ['%command' => "$exec list"]],
    ];
    $this->verifyMessages($expect, $jorge->messages);
    $this->assertTrue($tool->getStatus()->running);

    $tempDir->delete();
  }
}
