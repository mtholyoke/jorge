<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\JorgeTests\Mock\MockLogTrait;
use Psr\Log\LogLevel;

/**
 * Provides public methods to use for testing.
 */
trait MockToolPublicMethodsTrait {
  use MockLogTrait;

  /** @var array $stubJorge Data and parameters for test mocks */
  public $stubJorge = [
    'getPath' => NULL,
    'loadConfigFile' => [],
    'loadConfigFileWarning' => FALSE,
  ];

  /**
   * {@inheritDoc}
   */
  public function applyVerbosity($argv = '') {
    return parent::applyVerbosity($argv);
  }

  /**
   * {@inheritDoc}
   */
  public function disable() {
    return parent::disable();
  }

  /**
   * {@inheritDoc}
   */
  public function enable() {
    return parent::enable();
  }

  /**
   * {@inheritDoc}
   */
  public function exec($argv = '', $prompt = FALSE) {
    return parent::exec($argv);
  }

  /**
   * {@inheritDoc}
   */
  public function initialize() {
    return parent::initialize();
  }

  /**
   * Tags log messages with tool name and passes them to MockLogTrait::mockLog().
   *
   * {@inheritDoc}
   */
  public function log($level, $message, array $context = []) {
    $message = trim('{' . $this->getName() . '} ' . $message);
    $this->mockLog($level, $message, $context);
  }

  /**
   * Sets configuration.
   *
   * @param mixed $config The new configuration value/array/object
   * @return $this
   */
  public function setConfig($config) {
    $this->config = $config;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function setExecutable($executable) {
    return parent::setExecutable($executable);
  }

  /**
   * Sets verbosity so we can test different behaviors.
   *
   * This is not in the superclass, which gets its verbosity from the application.
   *
   * @param int $verbosity The verbosity level
   * @return $this
   */
  public function setVerbosity($verbosity) {
    $this->verbosity = $verbosity;
    return $this;
  }

  /**
   * Sets appropriate response for loadConfigFile.
   *
   * Override in the tool-specific mock class if necessary.
   *
   * @param string $project The project name
   */
  public function stubConfig($project) {
    $this->stubJorge['loadConfigFile'] = ['name' => $project];
    $this->stubJorge['loadConfigFileWarning'] = FALSE;
  }

  /**
   * Creates an object that response to some Jorge methods.
   */
  public function stubJorge() {
    if (!empty($this->jorge)) {
      return;
    }

    $this->jorge = new class($this) {
      public $tool;

      public function __construct($tool) {
        $this->tool = $tool;
      }

      public function getPath($subdir = NULL, $required = FALSE) {
        return $this->tool->stubJorge['getPath'];
      }

      public function loadConfigFile($file, $level = LogLevel::WARNING) {
        if ($this->tool->stubJorge['loadConfigFileWarning']) {
          $this->tool->log($level, 'Can’t read config file {%filename}', ['%filename' => $file]);
        }
        return $this->tool->stubJorge['loadConfigFile'];
      }
    };
  }

  /**
   * Saves what would have been printed so it can be checked.
   *
   * @param string|array $messages The message as an array of lines of a single string
   * @param int          $options  A bitmask of options
   * @see Symfony\Component\Console\Output\OutputInterface
   */
  public function writeln($messages, $options = 0) {
    $messages = (array) $messages;
    foreach ($messages as $message) {
      $this->messages[] = ['writeln', $message];
    }
  }
}
