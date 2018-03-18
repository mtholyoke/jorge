<?php

namespace MountHolyoke\Jorge\Tool;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Output\OutputInterface;

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
   * @throws LogicException when the tool name is empty
   */
  public function __construct($name = NULL) {
    if (!empty($name)) {
      $this->name = $name;
    }
    $this->configure();
    if (empty($this->getName()) {
      throw new LogicException('Tool name cannot be empty');
    }
  }

  /**
   * Alters the arguments/options to reflect the desired verbosity setting
   *
   * @param int verbosity constant from OutputInterface
   * @param mixed arguments/options for the command
   * @return mixed arguments/options plus verbosity
   */
  protected function applyVerbosity($verbosity, $argv = NULL) {
    return $argv;
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
   * Executes the tool command and returns the result array and status.
   *
   * @param string arguments and options for the command
   * @return array the command with its output and exit status
   */
  protected function exec($argv = '') {
    $command = trim($this->getExecutable() . ' ' . $argv);
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
   * Checks that the tool is enabled before running it.
   *
   * @param string arguments and options for the command
   * @return int exit status from the command
   */
  public function run($argv = '') {
    if (!$this->isEnabled()) {
      $this->log(
        LogLevel::WARNING,
        'Tool "{%tool}" is not enabled',
        ['%tool' => $this->getName()]
      );
      return;
    }
    return $this->runThis($argv);
  }

  /**
   * Runs the tool with the given subcommands/options.
   *
   * @param string arguments and options for the command
   * @return int exit status from the command
   */
  public function runThis($argv = '') {
    $output    = $this->getApplication()->output;
    $verbosity = $output->getVerbosity();
    $command   = $this->applyVerbosity($verbosity, $argv);

    $this->log(
      LogLevel::DEBUG,
      '$ {%executable} {%command}',
      ['%executable' => $this->getExecutable(), '%command' => $command]
    );

    $result = $this->exec($command);

    if ($verbosity != OutputInterface::VERBOSITY_QUIET) {
      $output->writeln(implode("\n", $result['output']));
    }

    return $result['status'];
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
