<?php

namespace MountHolyoke\Jorge\Command;

use MountHolyoke\Jorge\Helper\JorgeTrait;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides a Jorge command that can execute Drush commands.
 *
 * @link https://github.com/mtholyoke/jorge
 *
 * @author Jason Proctor <jproctor@mtholyoke.edu>
 * @copyright 2018 Trustees of Mount Holyoke College
 */
class DrushCommand extends Command {
  use JorgeTrait;

  /** @var string $drush_command The actual drush command with its arguments and options */
  protected $drush_command = '';

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
in the project but outside the main Drupal directory.

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
   * Executes the `drush` command.
   *
   * Assembles the drush command and passes it to the 'lando' tool.
   * @todo If I have a sequence of calls, could I share the Lando bootstrap?
   *
   * @param \Symfony\Component\Console\Input\InputInterface   $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return null|int
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $lando  = $this->jorge->getTool('lando');
    $drush  = trim('drush ' . $this->drush_command);
    $webdir = $this->findDrupal();

    if (!$lando->isEnabled()) {
      $this->log(LogLevel::ERROR, 'Cannot run without Lando');
      return;
    }
    chdir($webdir);
    if (!$lando->getStatus()->running) {
      $lando->run('start');
      $lando->updateStatus();
    }
    return $lando->run($drush);
  }

  /**
   * Identifies the Drupal directory based on appType.
   *
   * @todo This could (and should) be a _lot_ smarter.
   *
   * @return string The fully qualified path
   */
  protected function findDrupal() {
    $subdir = '';
    switch ($this->jorge->getConfig('appType')) {
      case 'drupal7':
        # In a non-Composer site, stay in project root.
        break;
      case 'drupal8':
        # In a Composer site, change to web directory.
        $subdir = 'web';
        break;
      default:
        # Not implemented yet.
        break;
    }
    return $this->jorge->getPath($subdir, TRUE);
  }

  /**
   * Initializes the `drush` command.
   *
   * Parses the command-line arguments and options to assemble the actual
   * command string to send to Drush.
   * @uses \MountHolyoke\Jorge\Helper\JorgeTrait::initializeJorge()
   *
   * @param \Symfony\Component\Console\Input\InputInterface   $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->initializeJorge();

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
  }
}
