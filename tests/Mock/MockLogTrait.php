<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

/**
 * Provides a mockLog() method for other mocks.
 */
trait MockLogTrait {
  /** @var array $messages Things that would have gone to console output */
  public $messages = [];

  /**
   * Saves what would have been printed so it can be checked.
   *
   * @param string|null $level   What log level to use, or NULL to ignore
   * @param string      $message May need $context interpolation
   * @param array       $context Variable substitutions for $message
   * @return array The original parameters
   * @see \Symfony\Component\Console\Logger\ConsoleLogger
   */
  public function mockLog($level, $message, array $context = []) {
    $levelString = ($level === NULL) ? 'NULL' : $level;
    $this->messages[] = [$levelString, $message, $context];
  }
}
