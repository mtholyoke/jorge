<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Tool\Tool;
use MountHolyoke\JorgeTests\Mock\MockLogTrait;
use MountHolyoke\JorgeTests\Mock\MockToolPublicMethodsTrait;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Supplants the Tool class so we can call protected functions and capture output.
 */
class MockTool extends Tool {
  use MockLogTrait;
  use MockToolPublicMethodsTrait;

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
    $this->mockLog($level, $message, $context);
  }

  /**
   * {@inheritDoc}
   */
  public function setApplication(Application $application, $executable = '') {
    $this->jorge = $application;
    $this->setExecutable($executable);
    return $this;
  }

  /**
   * Sets both $status and $enabled so we can test things that require it.
   *
   * @param mixed $status The status to save
   * @return $this
   */
  public function setStatus($status) {
    $this->status = $status;
    if (is_bool($status)) {
      if ($status) {
        $this->enable();
      } else {
        $this->disable();
      }
    }
    return $this;
  }
}
