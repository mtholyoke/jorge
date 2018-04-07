<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Tool\Tool;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This mock exists to call protected functions for coverage testing.
 */
class MockTool extends Tool {
  /** @var array $messages Things that would have gone to console output */
  public $messages = [];

  /** @var int $verbosity The verbosity level */
  protected $verbosity = OutputInterface::VERBOSITY_QUIET;

  /**
   * Replaces log() with a method that returns instead of printing.
   *
   * Note that this also ignores verbosity: all messages will be included.
   *
   * @param string|null $level   What log level to use, or NULL to ignore
   * @param string      $message May need $context interpolation
   * @param array       $context Variable substitutions for $message
   * @return array The original parameters
   * @see \Symfony\Component\Console\Logger\ConsoleLogger
   */
  protected function log($level, $message, array $context = []) {
    $levelString = ($level === NULL) ? 'NULL' : $level;
    $this->messages[] = [$levelString, $message, $context];
  }

  /**
   * {@inheritDoc}
   */
  public function setApplication(Application $application, $executable = '') {
    $this->setExecutable($executable);
    return $this;
  }

  /**
   * Overload to set enabled so we can test things that require it.
   *
   * @param mixed $status The status to save
   * @return $this
   */
  public function setStatus($status) {
    $this->enabled = $status;
    $this->status = $status;
    return $this;
  }
}
