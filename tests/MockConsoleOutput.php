<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests;

use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * This class exists to trap the normal output instead of printing it.
 *
 * As a first pass, we replace the output streams php://stdout and php://stderr
 * with php://output, which can be captured by PHPUnit. Alternative apporaches
 * (which may be necessary for advanced testing) include replacing or altering
 * those with php://memory or php://filter, and creating a new formatter.
 */
class MockConsoleOutput extends ConsoleOutput {
  private $stderr;
  private $jorge;

  /**
   * Capture a link back to Jorge for its messages[].
   */
  public function __construct($jorge, $verbosity) {
    parent::__construct($verbosity);
    $this->jorge = $jorge;
  }

  /**
   * N/A in test environment.
   */
  private function isRunningOS400() {
    return FALSE;
  }

  /**
   * php://output can be captured by PHPUnit, php://stdout can’t.
   *
   * This is only used for output that doesn’t go through logger or write().
   *
   * @return resource
   */
  private function openOutputStream() {
    return fopen('php://output', 'w');
  }

  /**
   * php://output can be captured by PHPUnit, php://stderr can’t.
   *
   * This is only used for output that doesn’t go through logger or write().
   *
   * @return resource
   */
  private function openErrorStream() {
    return fopen('php://output', 'w');
  }

  /**
   * When possible, save stuff directly to a place we can get it.
   *
   * {@inheritDoc}
   */
  public function write($messages, $newline = FALSE, $options = ConsoleOutput::OUTPUT_NORMAL) {
    $messages = (array) $messages;
    foreach ($messages as $message) {
      $this->jorge->messages[] = ['write', $message];
    }
  }

  /**
  * When possible, save stuff directly to a place we can get it.
  *
   * {@inheritDoc}
   */
  public function writeln($messages, $options = ConsoleOutput::OUTPUT_NORMAL) {
    $messages = (array) $messages;
    foreach ($messages as $message) {
      $this->jorge->messages[] = ['writeln', $message];
    }
  }
}
