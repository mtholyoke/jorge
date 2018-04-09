<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Tool;

use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * Provides standard methods to use for testing tool initialization.
 */
trait ToolInitTestTrait {
  /** @var \MountHolyoke\Jorge\Tool\Tool $this->tool The persistent MockTool for testing. */
  public $tool;

  public function checkInitWithoutExecutable() {
    $this->tool->initialize();
    $this->assertFalse($this->tool->isEnabled());
  }

  public function checkInitWithBadExecutable() {
    $bogus = $this->makeRandomString();
    $this->tool->setExecutable($bogus);
    $this->assertNull($this->tool->getExecutable());
    $this->checkInitWithoutExecutable();
  }

  public function checkInitWithoutConfig() {
    $this->tool->initialize();
    $this->assertFalse($this->tool->isEnabled());
  }

  public function checkInitWithEmptyConfig() {

  }

  public function checkInitWithValidConfig() {

  }

  /**
   * Runs the standard suite of tool initialization tests.
   */
  public function runAllToolInitTests($name) {
    $class = '\MountHolyoke\JorgeTests\Mock\Mock' . ucfirst($name) . 'Tool';
    $this->tool = new $class();

    $this->checkInitWithoutExecutable();
    $this->checkInitWithBadExecutable();

    // TODO: should this be 'echo'?
    $this->tool->setExecutable($name);

    # Prepare a stub Jorge for the mock tool.
    $this->tool->stubJorge();

    # Without a root directory.
    $this->checkInitWithoutConfig();

    # Set up a root directory.
    $tempDirectory = (new TemporaryDirectory())->create();
    $root = realpath($tempDirectory->path());
    $this->tool->stubJorge['getPath'] = $root;
    chdir($root);
    $this->tool->stubJorge['loadConfigFileWarning'] = TRUE;

    # Make sure the tool is not enabled and we have logged a warning.
    $this->checkInitWithoutConfig();

    # Create an empty config file.
    // $this->tool->stubConfig();

    // $this->checkInitWithEmptyConfig();

    // $this->checkInitWithValidConfig();

    $tempDirectory->delete();
    return $this->tool->messages;
  }
}
