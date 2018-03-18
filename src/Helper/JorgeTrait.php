<?php

namespace MountHolyoke\Jorge\Helper;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Parent class for Jorge commands to collect some common functionality.
 */
trait JorgeTrait{
  protected $jorge = NULL;
  protected $verbosity = OutputInterface::VERBOSITY_NORMAL;

  /**
   * Establish some properties that are common to all Jorge commands and tools.
   *
   * This should be called from the initialize() method in each. Note that for
   * tools it’s called during setup phase, but commands don’t call it until run().
   */
  protected function initializeJorge() {
    $this->jorge = $this->getApplication();
    $this->verbosity = $this->jorge->output->getVerbosity();
  }

  /**
   * Sends a message prefixed with command name to the application’s logger.
   *
   * @param string|NULL what log level to use, or NULL to ignore.
   * @param string the message
   * @param array variable substitutions for the message
   */
   protected function log($level, $message, array $context = []) {
     if ($level !== NULL) {
       $message = '{' . $this->getName() . '} ' . $message;
       $this->jorge->log($level, $message, $context);
     }
   }


}
