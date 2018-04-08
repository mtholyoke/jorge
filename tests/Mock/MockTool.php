<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Tool\Tool;
use MountHolyoke\JorgeTests\Mock\MockToolPublicMethodsTrait;
use Symfony\Component\Console\Application;

/**
 * Supplants the Tool class so we can call protected functions and capture output.
 */
class MockTool extends Tool {
  use MockToolPublicMethodsTrait;

  /**
   * Sets a couple instance variables without the usual setApplication() side effects.
   *
   * {@inheritDoc}
   */
  public function setApplication(Application $application, $executable = '') {
    $this->jorge = $application;
    $this->setExecutable($executable);
    return $this;
  }
}
