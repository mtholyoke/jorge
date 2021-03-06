<?php

namespace MountHolyoke\Jorge\Tool;

use MountHolyoke\Jorge\Helper\JorgeTrait;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Base class for tools.
 *
 * Mostly an adaptation of Symfony\Component\Console\Command\Command.
 *
 * This class is potentially usable without subclassing it, by providing
 * a name and an executable when it's added to the application. Overrides
 * to some or all of the following methods make it more useful:
 *   configure()
 *   initialize()
 *   updateStatus()
 *   applyVerbosity()
 * See further implementation details in README.md.
 *
 * @link https://github.com/mtholyoke/jorge
 *
 * @author Jason Proctor <jproctor@mtholyoke.edu>
 * @copyright 2018 Trustees of Mount Holyoke College
 */
class Tool {
  use JorgeTrait;

  /** @var mixed $config Tool-specific configuration */
  protected $config;

    /** @var boolean $enabled Indicates the tool is applicable to the current project */
  protected $enabled = FALSE;

    /** @var string $executable The executable command associated with this tool */
  protected $executable;

    /** @var \Symfony\Component\Console\Helper\HelperSet $helperSet */
  protected $helperSet = NULL;

    /** @var string $name The name of the tool, used as an index in the application */
  protected $name;

    /** @var mixed $status Tool-specific status report */
  protected $status;

  /**
   * @param string|NULL $name The name of the tool
   * @throws \Symfony\Component\Console\Exception\LogicException when the tool name is empty
   */
  public function __construct($name = NULL) {
    if (!empty($name)) {
      $this->name = $name;
    }
    $this->configure();
    if (empty($this->getName())) {
      throw new LogicException('Tool name cannot be empty');
    }
  }

  /**
   * Alters the arguments/options to include the verbosity setting.
   *
   * @param mixed $argv Arguments/options for the command
   * @return mixed
   */
  protected function applyVerbosity($argv) {
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
   * @param mixed $argv Arguments and options for the command
   * @param bool|null $prompt Require interaction mid-command
   * @return array The command with its output and exit status
   */
  protected function exec($argv = '', $prompt = FALSE) {
    $command = trim($this->getExecutable() . ' ' . $argv);
    if (empty($command)) {
      $this->log(LogLevel::ERROR, 'Cannot execute a blank command');
      return ['command' => '', 'status' => 1];
    }

    $return = ['command' => $command];
    $this->log(LogLevel::NOTICE, '$ {%command}', ['%command' => $command]);
    if ($prompt) {
      $process = new Process($command);
      $process->setInput(STDIN);
      $process->start();
      while ($process->isRunning()) {
        print $process->getIncrementalOutput();
        print $process->getIncrementalErrorOutput();
      }
      $return['output'] = [];
      $return['status'] = $process->getExitCode();
    } else {
      exec($command, $output, $status);
      $return['output'] = $output;
      $return['status'] = $status;
    }

    return $return;
  }

  /**
   * @return \Symfony\Component\Console\Application
   */
  public function getApplication() {
    return $this->jorge;
  }

  /**
   * Return a parameter from configuration.
   *
   * @param string|null $key     The key to get from config, NULL for all
   * @param mixed       $default The value to return if key not present
   */
  public function getConfig($key = NULL, $default = NULL) {
    if ($key === NULL) {
      if (isset($this->config)) {
        return $this->config;
      } else {
        return $default;
      }
    }
    if (isset($this->config) && array_key_exists($key, $this->config)) {
      return $this->config[$key];
    }
    return $default;
  }

  /**
   * @return string
   */
  public function getExecutable() {
    return $this->executable;
  }

  /**
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * @param boolean $update Whether to call updateStatus() before returning
   * @param mixed   $args   Arguments for updateStatus() if necessary
   * @return mixed
   */
  public function getStatus($update = FALSE, $args = NULL) {
    if (empty($this->status) || $update) {
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
   * @return boolean
   */
  public function isEnabled() {
    return $this->enabled;
  }

  /**
   * Checks that the tool is enabled before running it.
   *
   * @param mixed|null $argv Arguments and options for the command
   * @param bool|null $prompt Require interaction mid-command
   * @return null|int
   */
  public function run($argv = NULL, $prompt = NULL) {
    if (!$this->isEnabled()) {
      $this->log(LogLevel::ERROR, 'Tool not enabled');
      return;
    }
    return $this->runThis($argv, $prompt);
  }

  /**
   * Runs the tool with the given subcommands/options.
   *
   * @param mixed|null $argv Arguments and options for the command
   * @param bool|null $prompt Require interaction mid-command
   * @return null|int
   */
  public function runThis($argv = NULL, $prompt = NULL) {
    $command = $this->applyVerbosity($argv);

    $result = $this->exec($command, $prompt);

    if ($this->verbosity != OutputInterface::VERBOSITY_QUIET) {
      if (array_key_exists('output', $result)) {
        $this->writeln($result['output']);
      }
    }

    return $result['status'];
  }

  /**
   * Connects the tool with the application.
   *
   * @uses \MountHolyoke\Jorge\Helper\JorgeTrait::initializeJorge()
   *
   * @param \Symfony\Component\Console\Application $application
   * @param string $executable Command the user would type to use this tool
   * @return $this
   */
  public function setApplication(Application $application, $executable = '') {
    $this->jorge = $application;
    $this->helperSet = $application->getHelperSet();
    $this->initializeJorge();

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
   * @param string $executable A command the current user has permission to run
   * @return $this
   */
  protected function setExecutable($executable) {
    $executable = escapeshellcmd($executable);
    exec("which $executable", $output, $status);
    if ($status === 0 && count($output) == 1) {
      $this->executable = $output[0];
      $this->log(
        LogLevel::DEBUG,
        'Executable is "{%executable}"',
        ['%executable' => $this->getExecutable()]
      );
    } else {
      $this->log(
        LogLevel::ERROR,
        'Cannot set executable "{%executable}"',
        ['%executable' => $executable]
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
   * @param mixed $status The status to save
   * @return $this
   */
  public function setStatus($status) {
    $this->status = $status;
    return $this;
  }

  /**
   * Computes and saves a status.
   *
   * @param mixed $args Any arguments necessary to determine the status
   */
  public function updateStatus($args = NULL) {
    $this->setStatus($this->isEnabled());
  }
}
