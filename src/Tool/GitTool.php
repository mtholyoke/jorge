<?php

namespace MountHolyoke\Jorge\Tool;

use MountHolyoke\Jorge\Tool\Tool;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\ArrayInput;
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
   * {@inheritDoc}
   */
  protected function applyVerbosity($argv = '') {
    $flag = '';
    switch ($this->verbosity) {
      case OutputInterface::VERBOSITY_QUIET:
      case OutputInterface::VERBOSITY_NORMAL:
      default:
        break;
      case OutputInterface::VERBOSITY_VERBOSE:
        $flag = '-v';
        break;
      case OutputInterface::VERBOSITY_VERY_VERBOSE:
      case OutputInterface::VERBOSITY_DEBUG:
        $flag = '-vv';
        break;
    }
    return trim($argv . ' ' . $flag);
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
      chdir($this->jorge->getPath());
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
