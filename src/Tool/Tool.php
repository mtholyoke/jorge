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
  protected $config;
  protected $enabled = FALSE;
  protected $executable;
  protected $helperSet = NULL;
  protected $name;
  protected $status;

  /**
   * @param string|NULL the name of the tool
   */
  public function __construct($name = NULL) {
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

  /**
   * Disables the tool.
   */
  protected function disable() {
    $this->enabled = FALSE;
  }

  /**
   * Enables the tool.
   */
  protected function enable() {
    $this->enabled = TRUE;
  }

  /**
   * Runs the tool and returns the result array and status.
   */
  protected function exec($argv = NULL) {
    $command = $this->executable . ' ' . $argv;
    exec($command, $output, $status);
    return [
      'command' => $command,
      'output'  => $output,
      'status'  => $status,
    ];
  }

  /**
   * @return Application the application
   */
  protected function getApplication() {
    return $this->application;
  }

  /**
   * @return mixed the saved executable command for the tool
   */
  protected function getExecutable() {
    return $this->executable;
  }

  /**
   * @return mixed the name of the tool
   */
  public function getName() {
    return $this->name;
  }

  /**
   * @param boolean whether to call updateStatus() before returning
   * @param mixed arguments for updateStatus() if necessary
   * @return mixed the current status
   */
  public function getStatus($refresh = FALSE, $args = NULL) {
    if (empty($this->status) || $refresh) {
      $this->updateStatus($args);
    }
    return $this->status;
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

  /**
   * @return boolean whether the tool can be fully used
   */
  public function isEnabled() {
    return $this->enabled;
  }

  /**
   * Sends a message to the application’s logger.
   *
   * @param string|NULL what log level to use, or NULL to ignore.
   * @param string the message
   * @param array variable substitutions for the message
   */
   protected function log($level, $message, array $context = []) {
     if ($level !== NULL) {
       $this->getApplication()->log($level, $message, $context);
     }
   }

  /**
   * Runs the tool with the given subcommands/options.
   */
  public function run($argv = NULL) {
    if ($this->isEnabled()) {
      $command = $this->executable . ' ' . $argv;
      system($command);
    } else {
      $this->log(
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
  public function setApplication(Application $application = NULL, $executable = NULL) {
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
   * @return $this
   */
  protected function setExecutable($executable) {
    $executable = escapeshellcmd($executable);
    exec("which $executable", $output, $status);
    if ($status === 0 && count($output) == 1) {
      $this->executable = $output[0];
      $this->log(
        LogLevel::DEBUG,
        'Executable for tool "{%tool}" is "{%executable}"',
        ['%tool' => $this->getName(), '%executable' => $this->getExecutable()]
      );
    } else {
      $this->log(
        LogLevel::WARNING,
        'Cannot set executable "{%executable}" for tool "{%tool}"',
        ['%executable' => $executable, '%tool' => $this->getName()]
      );
    }
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

  /**
   * Sets the current status.
   *
   * @param mixed the status to save
   * @return $this
   */
  public function setStatus($status) {
    $this->status = $status;
    return $this;
  }

  /**
   * Computes and saves a status.
   *
   * @param mixed any arguments necessary to determine the status
   */
  public function updateStatus($args = NULL) {
    $this->setStatus($this->isEnabled());
  }
}
