<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Helper\ComposerApplication;
use MountHolyoke\Jorge\Tool\ComposerTool;
use MountHolyoke\JorgeTests\Mock\MockToolPublicMethodsTrait;
use PHPUnit\Framework\TestCase;

/**
 * Supplants the ComposerTool class so we can replace its Composer.
 */
class MockComposerTool extends ComposerTool {
  use MockToolPublicMethodsTrait;

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
}
