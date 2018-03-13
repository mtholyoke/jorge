<?php

namespace MountHolyoke\Jorge\Tool;

use Symfony\Component\Console\Application;

/**
 * Base class for tools.
 *
 * Mostly an adaptation of Symfony\Component\Console\Command\Command.
 */
class Tool {
  protected $application = NULL;
  protected $enabled = FALSE;
  protected $helperSet = NULL;
  protected $name;

  public function __construct($name = '') {
    if (!empty($name)) {
      $this->name = $name;
    }
    $this->configure();
  }

  /**
   * Establishes the tool.
   *
   * This function must call setName() unless one is provided to the constructor.
   */
  protected function configure() {
  }

  public function getName() {
    return $this->name;
  }

  /**
   * Sets up the tool in context of the application.
   *
   * Unlike commands, this is called in the process of adding the tool. It is
   * usually the first opportunity to determine whether the tool is enabled,
   * meaning it is used in the current project being supported by Jorge.
   */
  protected function initialize() {
  }

  public function isEnabled() {
    return $this->enabled;
  }

  public function setApplication(Application $application = NULL) {
    if (!empty($application)) {
      $this->application = $application;
      $this->helperSet = $application->getHelperSet();
    }
    $this->initialize();
    return $this;
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

  public function status($args) {
    return $this->isEnabled();
  }
}
