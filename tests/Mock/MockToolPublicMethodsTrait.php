<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

/**
 * Provides public methods to use for testing.
 */
trait MockToolPublicMethodsTrait {
  /** @var array $messages Things that would have gone to console output */
  public $messages = [];

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
   * Saves what would have been printed so it can be checked.
   *
   * @param string|null $level   What log level to use, or NULL to ignore
   * @param string      $message May need $context interpolation
   * @param array       $context Variable substitutions for $message
   * @return array The original parameters
   * @see \Symfony\Component\Console\Logger\ConsoleLogger
   */
  public function log($level, $message, array $context = []) {
    $levelString = ($level === NULL) ? 'NULL' : $level;
    $message = trim('{' . $this->getName() . '} ' . $message);
    $this->messages[] = [$levelString, $message, $context];
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
