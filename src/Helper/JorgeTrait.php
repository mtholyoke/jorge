<?php

namespace MountHolyoke\Jorge\Helper;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Parent class for Jorge commands to collect some common functionality.
 *
 * @link https://github.com/mtholyoke/jorge
 *
 * @author Jason Proctor <jproctor@mtholyoke.edu>
 * @copyright 2018 Trustees of Mount Holyoke College
 */
trait JorgeTrait {
  /** @var \MountHolyoke\Jorge\Jorge $jorge The running application */
  protected $jorge = NULL;

  /** @var int $verbosity The verbosity level specified on the command line */
  protected $verbosity = OutputInterface::VERBOSITY_NORMAL;

  /**
   * Establish some properties that are common to all Jorge commands and tools.
   *
   * This should be called from the initialize() method in each command (which
   * is not invoked until the command is being run). Tools get it automatically:
   * @used-by \MountHolyoke\Jorge\Tool\Tool::setApplication()
   */
  protected function initializeJorge() {
    $this->jorge = $this->getApplication();
    $this->verbosity = $this->jorge->getOutput()->getVerbosity();
  }

  /**
   * Sends a message prefixed with command name to the application’s logger.
   *
   * @param string|null $level   What log level to use, or NULL to ignore
   * @param string      $message May need $context interpolation
   * @param array       $context Variable substitutions for $message
   * @see Symfony\Component\Console\Logger\ConsoleLogger
   */
  protected function log($level, $message, array $context = []) {
    if ($level !== NULL) {
      $message = '{' . $this->getName() . '} ' . $message;
      $this->jorge->log($level, $message, $context);
    }
  }

  /**
   * Sends text directly to the application's output interface.
   *
   * @param string|array $messages The message as an array of lines of a single string
   * @param int          $options  A bitmask of options
   * @see Symfony\Component\Console\Output\OutputInterface
   */
  protected function writeln($messages, $options = 0) {
    $this->jorge->getOutput()->writeln($messages, $options);
  }
}
