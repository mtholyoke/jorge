<?php

namespace MountHolyoke\Jorge\Tool;

use Composer\Factory;
use Composer\IO\ConsoleIO;
use Composer\IO\NullIO;
use MountHolyoke\Jorge\Helper\ComposerApplication;
use MountHolyoke\Jorge\Tool\Tool;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides a Jorge tool that uses the Composer API to execute Composer commands.
 *
 * @link https://github.com/mtholyoke/jorge
 *
 * @author Jason Proctor <jproctor@mtholyoke.edu>
 * @copyright 2018 Trustees of Mount Holyoke College
 */
class ComposerTool extends Tool {
  /** @var MountHolyoke\Jorge\Helper\ComposerApplication $composerApplication The instance of Composer */
  protected $composerApplication = NULL;

  /**
   * {@inheritDoc}
   */
  protected function applyVerbosity($argv = []) {
    switch ($this->verbosity) {
      case OutputInterface::VERBOSITY_QUIET:
        $argv['-q'] = TRUE;
        break;
      case OutputInterface::VERBOSITY_NORMAL:
      default:
        break;
      case OutputInterface::VERBOSITY_VERBOSE:
      $argv['-v'] = TRUE;
        break;
      case OutputInterface::VERBOSITY_VERY_VERBOSE:
      $argv['-vv'] = TRUE;
        break;
      case OutputInterface::VERBOSITY_DEBUG:
      $argv['-vvv'] = TRUE;
        break;
    }
    return $argv;
  }

  /**
   * Joins arguments and options as if they were on the command line.
   *
   * @param array $argv Command, arguments, and options for run()
   * @return string The joined string of arguments and options
   */
  protected function argvJoin(array $argv = []) {
    if (array_key_exists('command', $argv)) {
      unset($argv['command']);
    }
    $joinable = [];
    foreach ($argv as $k => $v) {
      if (is_bool($v)) {
        if ($v) {
          $joinable[] = $k;
        }
      } elseif ($v === NULL) {
        $joinable[] = $k;
      } else {
        $joinable[] = "$k=$v";
      }
    }
    return implode(' ', $joinable);
  }

  /**
   * Establishes the `composer` tool.
   */
  protected function configure() {
    $this->setName('composer');
  }

  /**
   * Executes the tool command and returns the result array and status.
   *
   * Composer is a Symfony Console Application, so there’s no need to
   * trap its output to send back up the call stack for verbosity control.
   *
   * @param array $argv Arguments and options for the command
   * @param bool|null $prompt Require interaction mid-command
   * @return array The command passed to the Composer application and its exit code.
   */
  protected function exec($argv = [], $prompt = FALSE) {
    if ($argv === NULL || !isset($argv) || !is_array($argv)) {
      $argv = [];
    }
    $input = new ArrayInput($argv);
    $output = $this->jorge->getOutput();

    if (!array_key_exists('command', $argv)) {
      $argv['command'] = '';
    }
    $this->log(
      LogLevel::NOTICE,
      '% composer {%cmd} {%argv}',
      ['%cmd' => $argv['command'], '%argv' => $this->argvJoin($argv)]
    );
    $status = $this->composerApplication->run($input, $output);
    return [
      'command' => $input,
      'output' => '',
      'status' => $status,
    ];
  }

  /**
   * Creates a Composer object to use for running commands.
   */
  protected function initialize() {
    if (empty($this->getExecutable())) {
      return;
    }
    if (($rootPath = $this->jorge->getPath()) === NULL) {
      return;
    }

    # Fail silently if the current project doesn’t use Composer.
    $this->config = $this->jorge->loadConfigFile('composer.json', NULL);
    if (empty($this->config)) {
      return;
    }
    $composerJson = $rootPath . DIRECTORY_SEPARATOR . 'composer.json';

    $factory  = new Factory();
    $composer = $factory->createComposer(new NullIO, $composerJson, FALSE, $rootPath);
    $this->composerApplication = new ComposerApplication();
    $this->composerApplication->setComposer($composer);
    $this->composerApplication->setAutoExit(FALSE);

    if (!empty($this->composerApplication)) {
      $this->enable();
    }
  }
}
