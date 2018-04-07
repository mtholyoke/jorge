<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Jorge;
use MountHolyoke\JorgeTests\Mock\MockConsoleOutput;
use MountHolyoke\JorgeTests\Mock\MockLogTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Supplants the Jorge class so we can capture its output for testing.
 */
class MockJorge extends Jorge {
  use MockLogTrait;

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

  /**
   * @param string $rootPath
   *   The directory serving as project root for this test
   */
  public function __construct($rootPath) {
    parent::__construct();
    $this->rootPath = $rootPath;
    $this->output = new MockConsoleOutput($this, parent::getOutput()->getVerbosity());
    $this->logger = new ConsoleLogger($this->output);
    $this->setAutoExit(FALSE);
  }

  /**
   * Returns the $rootPath set by __construct().
   *
   * This is necessary because findRootPath is called by configure(), which we
   * can’t mock because it needs to be tested.
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
    $this->mockLog($level, $message, $context);
  }

  /**
   * {@inheritDoc}
   */
  public function run(InputInterface $input = NULL, OutputInterface $output = NULL) {
    return parent::run($input, $this->getOutput());
  }
}
