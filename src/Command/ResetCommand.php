<?php

namespace MountHolyoke\Jorge\Command;

use MountHolyoke\Jorge\Helper\JorgeTrait;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ResetCommand extends Command {
  use JorgeTrait;

  # Config parameters necessary for this command
  protected $appType;
  protected $params;

  /**
   * Establishes the `reset` command with its command-line options and default parameters.
   */
  protected function configure() {
    $this
      ->setName('reset')
      ->setDescription('Aligns code, database, and files to a specified state')
      ->addOption('branch',   'b', InputOption::VALUE_OPTIONAL, 'Git branch to use', 'master')
      ->addOption('database', 'd', InputOption::VALUE_OPTIONAL, 'Environment to load database from', 'dev')
      ->addOption('files',    'f', InputOption::VALUE_OPTIONAL, 'Environment to copy files from', 'dev')
      ->addOption('username', 'u', InputOption::VALUE_OPTIONAL, 'Admin account to have local password set')
      ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Local password for admin account')
      ->setHelp('This command updates the local git environment to the latest master, copies the latest database and files from the specified environment on Pantheon, and imports the default config suitable for a hands-on development instance.')
    ;

    # These can be set by config.yml, and set or overridden by command line options
    $this->params = [
      'branch'   => 'master',
      'database' => 'dev',
      'files'    => 'dev',
      'rsync'    => TRUE,
      'username' => '',
      'password' => '',
    ];
  }

  /**
   * Processes config and command-line options to set parameters.
   *
   * @param InputInterface $input
   * @param OutputInterface $output
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->initializeJorge();
    $this->appType = $this->jorge->getConfig('appType', '');
    $config = $this->jorge->getConfig('reset', []);
    foreach (array_keys($this->params) as $var) {
      if (array_key_exists($var, $config) && !empty($config[$var])) {
        $this->params[$var] = $config[$var];
      }
      if ($input->hasOption($var) && $input->getOption($var)) {
        $this->params[$var] = $input->getOption($var);
      }
    }

    $this->log(LogLevel::DEBUG, 'Parameters:');
    foreach (array_keys($this->params) as $var) {
      $this->log(LogLevel::DEBUG, sprintf("  %-8s => '%s'", $var, $this->params[$var]));
    }
  }

  /**
   * Prompts the user if an admin account is specified in the parameters without
   * a password. If the user does not provide one, the password won’t be reset.
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    if (!empty($this->params['username']) && empty($this->params['password'])) {
      $helper = $this->getHelper('question');
      $question = new Question('Enter a password for ' . $this->params['username'] . ': ');
      // $question->setHidden(TRUE);
      // $question->setHiddenFallback(TRUE);
      $this->params['password'] = $helper->ask($input, $output, $question);
    }
  }

  /**
   * Executes the `reset` command.
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    switch ($this->appType) {
      case 'drupal8':
        $this->executeDrupal8();
        break;
      case 'jorge':
        // TODO: test whether this is a separate dev instance of Jorge.
        $this->log(LogLevel::WARNING, 'Can’t reset self');
        break;
      case '':
        $this->log(LogLevel::ERROR, 'No application type specified');
        break;
      default:
        $this->log(
          LogLevel::ERROR,
          'Unrecognized application type "{%appType}"',
          ['%appType' => $this->appType]
        );
        break;
    }
  }

  /**
   * Creates the list of steps necessary to reset a Drupal 8 project, then calls
   * a separate function to enact them.
   */
  protected function executeDrupal8() {
    $lando = $this->jorge->getTool('lando');

    # Do some stuff in the project root
    chdir($this->jorge->getPath());
    if (!$lando->getStatus()->running) {
      $lando->run('start');
    }
    $lando_pull = 'lando pull --code=none --database=' . $this->params['database'] . ' --files=' . $this->params['files'];
    if ($this->params['rsync']) {
      $lando_pull .= ' --rsync';
    }
    $steps = [
      'git checkout ' . $this->params['branch'],
      'git pull',
      'composer install',
      $lando_pull,
    ];
    foreach ($steps as $step) {
      $this->processStep($step);
    }

    $drush = $this->jorge->find('drush');
    $drushSequence = [
      ['drush_command' => ['cr']                                 ],
      ['drush_command' => ['csim', 'config_dev'], '--yes' => TRUE],
      ['drush_command' => ['updb'],               '--yes' => TRUE],
    ];
    if (!empty($this->params['username']) && !empty($this->params['password'])) {
      $drushSequence[] = [
        'drush_command' => [
          'upwd',
          $this->params['username'],
          '--password="' . $this->params['password'] . '"',
        ],
      ];
    }
    $drushSequence[] = ['drush_command' => ['cr']];

    foreach ($drushSequence as $step) {
      $drushInput = new ArrayInput($step);
      $drush->run($drushInput, $this->jorge->output);
    }
  }

  /**
   * Performs a step, with appropriate verbosity.
   *
   * TODO: This will need to be abstracted somewhere else when we have more than
   * one Jorge command that needs it. Probably we can be a lot smarter about
   * verbosity, too.
   *
   * @throws RuntimeException
   */
  private function processStep($step) {
    $this->log(LogLevel::NOTICE, '$ ' . $step);
    $result = '';

    if ($this->verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
      system($step, $status);
    } else {
      exec($step, $result, $status);
    }
    if ($status) {
      $error = 'Command exited with nonzero status.';
      if (is_array($result)) {
        $result = implode("\n", $result);
      }
      $message = sprintf("> %s\n%s\n%s", $step, $error, $result);
      throw new RuntimeException($message, $status);
    }
  }
}
