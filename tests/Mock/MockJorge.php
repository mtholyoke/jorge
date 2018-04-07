<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Jorge;
use MountHolyoke\JorgeTests\Mock\MockConsoleOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This is a wrapper class so we can capture the output, since we don’t
 * otherwise have a mechanism to substitute a more traditional mock.
 */
class MockJorge extends Jorge {
  /** {@inheritDoc} */
  private $config = [];

  /** {@inheritDoc} */
  private $input;

  /** {@inheritDoc} */
  private $logger;

  /** {@inheritDoc} */
  private $output;

  /** {@inheritDoc} */
  private $rootPath;

  /** {@inheritDoc} */
  private $tools;

  /** @var array $messages Things that would have gone to console output */
  public $messages = [];

  /**
   * Instantiates the object and replaces its output interfaces.
   *
   * @param string $rootPath The temporary directory serving as root, because
   *                         we have to stub findRootPath() also.
   */
  public function __construct($rootPath) {
    parent::__construct();
    $this->rootPath = $rootPath;
    $this->output = new MockConsoleOutput($this, parent::getOutput()->getVerbosity());
    $this->logger = new ConsoleLogger($this->output);
    $this->setAutoExit(FALSE);
  }

  /**
   * Stub because we can’t inherit a private function.
   */
  private static function findRootPath() {
    return $this->rootPath;
  }

  /**
   * {@inheritDoc}
   */
  public function getOutput() {
    return $this->output;
  }

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
  public function log($level, $message, array $context = []) {
    $levelString = ($level === NULL) ? 'NULL' : $level;
    $this->messages[] = [$levelString, $message, $context];
  }

  /**
   * {@inheritDoc}
   */
  public function run(InputInterface $input = NULL, OutputInterface $output = NULL) {
    return parent::run(NULL, $this->getOutput());
  }
}
