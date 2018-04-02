<?php

namespace MountHolyoke\Jorge\Tool;

use MountHolyoke\Jorge\Tool\Tool;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides a Jorge tool that can execute Git commands.
 *
 * @link https://github.com/mtholyoke/jorge
 *
 * @author Jason Proctor <jproctor@mtholyoke.edu>
 * @copyright 2018 Trustees of Mount Holyoke College
 */
class GitTool extends Tool {
  /**
   * Alters the arguments/options to include the verbosity setting.
   *
   * Git’s verbosity options vary based on which command is running.
   * If this method is called witb an array, the first element is the
   * command and all others are its arguments/options. The return value
   * is the string that will actually be used on the command line. This
   * saves us from needing to override exec() to handle multiple things.
   * If this method is called with a string, it’s returned unchanged.
   * @todo Add more Git commands
   *
   * @param mixed $argv The command and arguments/options
   * @return string The entire command line as it should be run
   */
  protected function applyVerbosity($argv = '') {
    # If we get a string, we don’t know which git command it is, so
    # we can’t apply verbosity. Return it unchanged.
    if (!is_array($argv) || empty($argv)) {
      return $argv;
    }

    $verbosityMap = [
      OutputInterface::VERBOSITY_QUIET        => '2>&1',
      OutputInterface::VERBOSITY_NORMAL       => '',
      OutputInterface::VERBOSITY_VERBOSE      => '-v',
      OutputInterface::VERBOSITY_VERY_VERBOSE => '-v',
      OutputInterface::VERBOSITY_DEBUG        => '-v',
    ];

    # Update the verbosity map based on the command.
    $command = array_shift($argv);
    switch ($command) {
      case 'checkout':
        $verbosityMap[OutputInterface::VERBOSITY_QUIET]        = '-q 2>&1';
        $verbosityMap[OutputInterface::VERBOSITY_VERBOSE]      = '';
        $verbosityMap[OutputInterface::VERBOSITY_VERY_VERBOSE] = '';
        $verbosityMap[OutputInterface::VERBOSITY_DEBUG]        = '';
        break;
      case 'pull':
        # Use the default above.
        break;
      case 'status':
        $verbosityMap[OutputInterface::VERBOSITY_VERY_VERBOSE] = '-vv';
        $verbosityMap[OutputInterface::VERBOSITY_DEBUG]        = '-vv';
        break;
      default:
        # Use the default above.
        break;
    }

    if (array_key_exists($this->verbosity, $verbosityMap)) {
      $argv[] = $verbosityMap[$this->verbosity];
    }
    return trim($command . ' ' . implode(' ', $argv));
  }

  /**
   * Establishes the `git` tool.
   */
  protected function configure() {
    $this->setName('git');
    $this->setStatus((object)['clean' => FALSE]);
  }

  /**
   * Enables the `git` tool if we have an executable and the project is a git repo
   */
  protected function initialize() {
    if (!empty($this->getExecutable())) {
      if (($rootPath = $this->jorge->getPath()) === NULL) {
        return;
      }
      chdir($rootPath);
      $exec = $this->exec('status 2>&1');
      if ($exec['status'] == 0) {
        $this->enable();
        $this->updateStatus($exec['output']);
      }
    }
  }

  /**
   * Determines where there are changes to be staged or committed.
   */
  public function updateStatus($output = []) {
    if (empty($output)) {
      $exec = $this->exec('status 2>&1');
      $output = $exec['output'];
    }
    $status = $this->getStatus();
    $status->clean = (strpos(end($output), 'nothing to commit') !== FALSE);
    $this->setStatus($status);
  }
}
