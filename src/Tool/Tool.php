<?php

namespace MountHolyoke\Jorge\Tool;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Application;

/**
 * Base class for tools.
 *
 * Mostly an adaptation of Symfony\Component\Console\Command\Command.
 */
class Tool {
  protected $application = NULL;
  protected $enabled = FALSE;
  protected $executable;
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

  protected function getExecutable() {
    return $this->executable;
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

  /**
   * Runs the tool with the given subcommands/options.
   */
  public function run($argv = NULL) {
    if ($this->isEnabled()) {
      $command = $this->executable . ' ' . $argv;
      system($command);
    } else {
      $this->application->log(
        LogLevel::WARNING,
        'Tool "{%tool}" is not enabled',
        ['%tool' => $this->getName()]
      );
    }
  }

  /**
   * Runs the tool as in run(), but without checking isEnabled().
   */
  public function runAlways($argv = NULL) {
    $command = $this->executable . ' ' . $argv;
    system($command);
  }

  /**
   * Connects the tool with the application.
   *
   * @param Application the running instance of Jorge
   * @param string command the user would type to use this tool
   * @return this
   */
  public function setApplication(Application $application = NULL, $executable = '') {
    if (!empty($application)) {
      $this->application = $application;
      $this->helperSet = $application->getHelperSet();
    }

    if (empty($this->getExecutable())) {
      if (empty($executable)) {
        $executable = $this->getName();
      }
      $this->setExecutable($executable);
    }

    $this->initialize();
    return $this;
  }

  /**
   * Sets the command-line executable for this tool.
   *
   * @param string a command the current user has permission to run
   */
  protected function setExecutable($executable) {
    $executable = escapeshellcmd($executable);
    exec("which $executable", $output, $status);
    if ($status === 0 && count($output) == 1) {
      $this->executable = $output[0];
      $this->application->log(
        LogLevel::DEBUG,
        'Executable for tool "{%tool}" is "{%executable}"',
        ['%tool' => $this->getName(), '%executable' => $this->getExecutable()]
      );
    } else {
      $this->application->log(
        LogLevel::WARNING,
        'Cannot set executable "{%executable}" for tool "{%tool}"',
        ['%executable' => $executable, '%tool' => $this->getName()]
      );
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

  public function status($args) {
    return $this->isEnabled();
  }
}
