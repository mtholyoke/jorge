<?php

namespace MountHolyoke\Jorge\Command;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DrushCommand extends Command {
  protected $drush_command = '';
  protected $jorge;

  /**
   * Establishes the `drush` command.
   */
  protected function configure() {
    $this
      ->setName('drush')
      ->setDescription('Executes `lando drush` in the correct directory')
      ->addArgument('drush_command', InputArgument::IS_ARRAY, 'Drush command to execute')
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Drush option: Answer "yes" to all Drush prompts')
      ->setHelp("
This command is a simple wrapper for `lando drush` to make it executable
outside the web directory.

Currently, the only Drush option it supports is -y/--yes. Use quotes or
double hyphen to escape others (including -h and other Jorge options):
  jorge drush 'foo --bar'
  jorge drush \"foo --bar\"
  jorge drush foo -- --bar

Jorge’s verbosity is is passed to both Lando and Drush; if you want it to
only apply to Drush, you can escape -v/--verbose as above.
");
  }

  /**
   * Initializes the `drush` command.
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->verbosity = $output->getVerbosity();
    $this->jorge = $this->getApplication();
    $arguments = $input->getArgument('drush_command');
    if (!empty($arguments)) {
      if ($input->hasOption('yes') && $input->getOption('yes')) {
        $arguments[] = '--yes';
      }
      $this->drush_command = implode(' ', $arguments);
    }
    if ($this->verbosity > OutputInterface::VERBOSITY_NORMAL) {
      $this->drush_command = trim($this->drush_command . ' --verbose');
    }

    $this->jorge->log(
      LogLevel::DEBUG,
      'Drush command: "{%command}"',
      ['%command' => $this->drush_command]
    );
  }

  /**
   * Executes the `drush` command.
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $lando  = $this->jorge->getTool('lando');
    $drush  = trim('drush ' . $this->drush_command);
    $webdir = $this->jorge->getPath('web', TRUE);

    if (!$lando->isEnabled()) {
      $this->jorge->log(LogLevel::ERROR, 'Cannot run Drush without Lando');
      return;
    }
    chdir($webdir);
    if (!$lando->getStatus()->running) {
      $lando->run('start');
    }
    $lando->run($drush);
  }
}
