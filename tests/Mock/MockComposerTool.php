<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Helper\ComposerApplication;
use MountHolyoke\Jorge\Tool\ComposerTool;
use PHPUnit\Framework\TestCase;

/**
 * Supplants the ComposerTool class so we can replace its Composer.
 */
class MockComposerTool extends ComposerTool {
  /**
   * {@inheritDoc}
   */
  public function configure() {
    $this->setName('mockComposer');
  }

  /**
   * Replaces the Composer being used to run commands with a mock.
   *
   * @param \PHPUnit\Framework\TestCase $test The current TestCase
   * @return $this
   */
  public function mockComposerApplication($test) {
    $mock = $test->getMockBuilder(ComposerApplication::class)
                 ->setMethods(['run'])
                 ->getMock();

    $mock->method('run')->willReturn(0);

    $this->composerApplication = $mock;
    return $this;
  }

  /**
   * Calls exec() for unfiltered testing.
   *
   * @param mixed|null $argv The arguments to pass to exec()
   */
  public function mockExec($argv = NULL) {
    return $this->exec($argv);
  }

  /**
   * Changes the tool’s verbosity after creation so we can test it.
   *
   * @param int $verbosity The verbosity level
   * @return $this
   */
  public function setVerbosity($verbosity) {
    $this->verbosity = $verbosity;
    return $this;
  }
}
