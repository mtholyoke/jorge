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

  /**
   * @var string $stubJorgeGetPath
   *   Path to be returned by $this->jorge->getPath()
   */
  public $stubJorgeGetPath = NULL;

  /**
   * @var array $stubJorgeLoadConfigFile
   *   Config to be returned by $this->jorge->loadConfigFile()
   */
  public $stubJorgeLoadConfigFile = [];

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
  public function exec($argv = '') {
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

  public function setJorge($jorge) {
    $this->jorge = $jorge;
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
        return $this->tool->stubJorgeGetPath;
      }

      public function loadConfigFile($file, $level = LogLevel::WARNING) {
        return $this->tool->stubJorgeLoadConfigFile;
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
