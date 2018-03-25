<?php

namespace MountHolyoke\Jorge\Tool;

use Composer\Factory;
use Composer\IO\ConsoleIO;
use Composer\IO\NullIO;
use MountHolyoke\Jorge\Helper\ComposerApplication;
use MountHolyoke\Jorge\Tool\Tool;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\ArrayInput;

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
   * Establishes the `composer` tool.
   */
  protected function configure() {
    $this->setName('composer');
  }

  /**
   * Creates a Composer object to use for running commands.
   */
  protected function initialize() {
    $factory = new Factory();
    $rootPath = $this->jorge->getPath();
    $composerJson = $rootPath . '/composer.json';
    if (!is_file($composerJson)) {
      return;
    }
    $composer = $factory->createComposer(new NullIO, $composerJson, FALSE, $rootPath);
    $this->composerApplication = new ComposerApplication();
    $this->composerApplication->setComposer($composer);
    $this->composerApplication->setAutoExit(FALSE);

    if (!empty($this->composerApplication)) {
      $this->enable();
    }
  }

  public function update($dryRun = TRUE) {
    // $update = $this->composerApplication->find('update');
    $input = new ArrayInput([
      'command' => 'update',
      '--dry-run' => TRUE,
    ]);
    // $console = new ConsoleIO($input, $this->jorge->getOutput(), $this->helperSet);
    $output = $this->jorge->getOutput();
    $this->composerApplication->run($input, $output);
    // $update->run($input, $output);
  }
}
