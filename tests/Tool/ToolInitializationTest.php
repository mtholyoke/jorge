<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Tool;

use MountHolyoke\JorgeTests\Mock\MockJorge;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * Tests initialization under various circumstances.
 */
final class ToolInitializationTest extends TestCase {
  use OutputVerifierTrait;
  use RandomStringTrait;

  /** @var \MountHolyoke\JorgeTests\Mock\MockJorge */
  public $jorge;

  /** @var string The effective project root */
  public $root;

  /** @var \Spatie\TemporaryDirectory\TemporaryDirectory */
  public $tempDir;

  /**
   * @var array $tools The names of tools being tested, along with any
   *   additional info for specific tests (not currently used).
   */
  public $tools;

  public function __construct($name = null, array $data = [], $dataName = '') {
    parent::__construct($name, $data, $dataName);
    $this->tools = [
      'composer' => [],
      'git'      => [],
      'lando'    => [],
    ];
  }

  protected function setUp(): void {
    $this->tempDir = (new TemporaryDirectory())->create();
    $this->root = realpath($this->tempDir->path());
    chdir($this->root);
    mkdir('.jorge');
    touch('.jorge' . DIRECTORY_SEPARATOR . 'config.yml');
    $this->jorge = new MockJorge($this->root);
  }

  protected function tearDown(): void {
    $this->jorge = NULL;
    $this->tempDir->delete();
  }

  public function testInitializeWithoutRoot(): void {
    unlink('.jorge' . DIRECTORY_SEPARATOR . 'config.yml');
    rmdir('.jorge');
    $this->jorge->configure();

    # Verify that we did not run `git status`.
    $startup = [
      [LogLevel::WARNING, 'Can’t find project root'],
      [LogLevel::DEBUG,   '{composer} Executable is "{%executable}"'],
      [LogLevel::DEBUG,   '{git} Executable is "{%executable}"'],
      [LogLevel::DEBUG,   '{lando} Executable is "{%executable}"'],
      ['NULL',            'Can’t read config file {%filename}'],
    ];
    $this->verifyMessages($startup, $this->jorge->messages);

    # Verify that the tools were not enabled.
    foreach (array_keys($this->tools) as $name) {
      $tool = $this->jorge->getTool($name);
      $this->assertFalse($tool->isEnabled());
    }
  }

  public function testInitializeWithoutExecutable(): void {
    $this->jorge->configure();

    foreach (array_keys($this->tools) as $name) {
      # Create a mock tool with a bogus executable.
      $class = '\MountHolyoke\JorgeTests\Mock\Mock' . ucfirst($name) . 'Tool';
      $tool  = new $class();
      $exec  = $this->makeRandomString();
      $this->jorge->addTool($tool, $exec);

      # Verify that the tool was not enabled, and that they logged errors.
      $this->assertFalse($tool->isEnabled());
      $expect = [
        [LogLevel::ERROR, '{' . $tool->getName() . '} Cannot set executable "{%executable}"'],
      ];
      $this->verifyMessages($expect, $tool->messages);
    }
  }

  public function testInitializeWithoutConfig(): void {
    $this->jorge->configure();

    # Verify that the tools tried to load their config.
    $startup = [
      [LogLevel::NOTICE, 'Project root: {%root}'],
      [LogLevel::DEBUG,  '{composer} Executable is "{%executable}"'],
      ['NULL',           'Can’t read config file {%filename}'],
      [LogLevel::DEBUG,  '{git} Executable is "{%executable}"'],
      [LogLevel::NOTICE, '{git} $ {%command}'],
      [LogLevel::DEBUG,  '{lando} Executable is "{%executable}"'],
      ['NULL',           'Can’t read config file {%filename}'],
    ];
    $this->verifyMessages($startup, $this->jorge->messages);
    $this->jorge->messages = [];

    # Verify that the tools were not enabled.
    foreach (array_keys($this->tools) as $name) {
      $tool = $this->jorge->getTool($name);
      $this->assertFalse($tool->isEnabled());
    }
  }

  public function testInitializeWithEmptyConfig(): void {
    touch('composer.json');
    mkdir('.git');
    touch('.lando.yml');
    $this->jorge->configure();

    # Verify that the tools tried to load their config.
    $startup = [
      [LogLevel::NOTICE, 'Project root: {%root}'],
      [LogLevel::DEBUG,  '{composer} Executable is "{%executable}"'],
      [LogLevel::DEBUG,  '{git} Executable is "{%executable}"'],
      [LogLevel::NOTICE, '{git} $ {%command}'],
      [LogLevel::DEBUG,  '{lando} Executable is "{%executable}"'],
    ];
    $this->verifyMessages($startup, $this->jorge->messages);
    $this->jorge->messages = [];

    # Verify that the tools were not enabled.
    foreach (array_keys($this->tools) as $name) {
      $tool = $this->jorge->getTool($name);
      $this->assertFalse($tool->isEnabled());
    }
  }

  public function testInitializeWithValidConfig(): void {
    # Establish valid configuration.
    $project = $this->makeRandomString();
    $composerConfig = ['name' => "test/$project"];
    file_put_contents('composer.json', json_encode($composerConfig));
    exec('git init .');
    $landoConfig = "name: $project\n";
    file_put_contents('.lando.yml', $landoConfig);
    $this->jorge->configure();

    # Verify that the tools tried to load their config.
    $startup = [
      [LogLevel::NOTICE, 'Project root: {%root}'],
      [LogLevel::DEBUG,  '{composer} Executable is "{%executable}"'],
      [LogLevel::DEBUG,  '{git} Executable is "{%executable}"'],
      [LogLevel::NOTICE, '{git} $ {%command}'],
      [LogLevel::DEBUG,  '{lando} Executable is "{%executable}"'],
    ];
    $this->verifyMessages($startup, $this->jorge->messages);
    $this->jorge->messages = [];

    # Verify that the tools were enabled and config was loaded.
    foreach (array_keys($this->tools) as $name) {
      $tool = $this->jorge->getTool($name);
      $this->assertTrue($tool->isEnabled());

      switch ($name) {
        case 'composer':
          $this->assertSame("test/$project", $tool->getConfig('name'));
          break;
        case 'git':
          // TODO: How can we test that Git found and read repo info?
          break;
        case 'lando':
          $this->assertSame($project, $tool->getConfig('name'));
          break;
        default:
          break;
      }
    }
  }

}
