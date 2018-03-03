<?php

namespace MountHolyoke\Jorge\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetCommand extends Command {
  // Default values can be overridden by config.yml
  protected $rootPath;
  protected $appType;
  protected $logger;
  protected $branch   = 'master';
  protected $database = 'dev';
  protected $files    = 'dev';
  protected $rsync    = TRUE;

  /**
   * Establishes the `reset` command and updates config if necessary.
   */
  protected function configure() {
    $this
      ->setName('honk')
      ->setDescription('Honks at you')
      ->setHelp('This command is a simple output test to verify that Jorge is running.')
    ;
  }

  /**
   * Prepares the `reset` command.
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $jorge = $this->getApplication();
    $this->rootPath = $jorge->rootPath;
    $this->appType  = $jorge->appType;
    $this->logger   = $jorge->logger;
    if (array_key_exists('reset', $jorge->config)) {
      $config = $jorge->config['reset'];
      foreach (['branch', 'database', 'files', 'rsync'] as $var) {
        if (array_key_exists($var, $config) && !empty($config[$var])) {
          $this->{$var} = $config[$var];
        }
      }
    }
  }

  /**
   * Executes the `reset` command.
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $cwd = getcwd();
    $output->writeln('Honk!');
  }
}
