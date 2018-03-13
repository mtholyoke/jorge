<?php

namespace MountHolyoke\Jorge\Tool;

use Symfony\Component\Console\Application;

/**
 * Base class for tools.
 *
 * Mostly an adaptation of Symfony\Component\Console\Command\Command.
 */
class Tool {
  private $name;
  private $application;
  private $helperSet;

  public function __construct()
  {
    $this->configure();
  }

  protected function configure() {
  }

  public function getName() {
    return $this->name;
  }

  public function setApplication(Application $application = NULL) {
    $this->application = $application;
    if ($application) {
      $this->helperSet = $application->getHelperSet();
    } else {
      $this->helperSet = NULL;
    }
  }

  /**
   * Sets the name of the tool.
   *
   * @param string $name The tool name
   * @return $this
   */
  protected function setName($name) {
    $this->name = $name;
    return $this;
  }
}
