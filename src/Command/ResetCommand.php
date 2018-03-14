<?php

namespace MountHolyoke\Jorge\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ResetCommand extends Command {
  # Useful properties of the Jorge application object
  protected $rootPath;
  protected $logger;
  protected $verbosity;
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
      ->setDefinition(new InputDefinition([
        new InputOption('branch',   'b', InputOption::VALUE_OPTIONAL, 'Git branch to use'),
        new InputOption('database', 'd', InputOption::VALUE_OPTIONAL, 'Environment to load database from'),
        new InputOption('files',    'f', InputOption::VALUE_OPTIONAL, 'Environment to copy files from'),
        new InputOption('username', 'u', InputOption::VALUE_OPTIONAL, 'Admin account to have local password set'),
        new InputOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Local password for admin account'),
      ]))
      ->setHelp('This command updates the local git environment to the latest master, copies the latest database and files from the specified environment on Pantheon, and imports the default config suitable for a hands-on development instance.')
    ;

    # Defaults can be overridden by config.yml
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
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $jorge = $this->getApplication();
    $this->rootPath = $jorge->rootPath;
    $this->logger   = $jorge->logger;
    $this->appType  = array_key_exists('appType', $jorge->config) ? $jorge->config['appType'] : '';
    if (array_key_exists('reset', $jorge->config)) {
      $config = $jorge->config['reset'];
      foreach (array_keys($this->params) as $var) {
        if (array_key_exists($var, $config) && !empty($config[$var])) {
          $this->params[$var] = $config[$var];
        }
        if ($input->hasOption($var) && $input->getOption($var)) {
          $this->params[$var] = $input->getOption($var);
        }
      }
    }

    $this->logger->debug('Parameters for reset:');
    foreach (array_keys($this->params) as $var) {
      $this->logger->debug(sprintf("  %-8s => '%s'", $var, $this->params[$var]));
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
    $verbosity = $output->getVerbosity();
    switch ($this->appType) {
      case 'drupal8':
        $this->executeDrupal8($verbosity);
        break;
      case 'jorge':
        // TODO: test whether this is a separate dev instance of Jorge.
        $this->logger->warning('Can’t reset self');
        break;
      case '':
        $this->logger->error('No application type specified');
        break;
      default:
        $this->logger->error('Unrecognized application type "' . $this->appType . '"');
    }
  }

  /**
   * Creates the list of steps necessary to reset a Drupal 8 project, then calls
   * a separate function to enact them.
   */
  protected function executeDrupal8($verbosity = OutputInterface::VERBOSITY_NORMAL) {
    $jorge = $this->getApplication();
    $lando = $jorge->getTool('lando');
    $cwd = getcwd();

    # Do some stuff in the project root
    if ($cwd != $this->rootPath) {
      $this->logger->notice('$ cd ' . $this->rootPath);
      chdir($this->rootPath);
    }
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
      $this->processStep($step, $verbosity);
    }

    # Do some stuff in the web subdirectory
    $this->logger->notice('$ cd web');
    chdir('web');
    $steps = [
      'lando drush cr',
      'lando drush csim config_dev --yes',
      'lando drush updb --yes',
    ];
    if (!empty($this->params['username']) && !empty($this->params['password'])) {
      $steps[] = 'lando drush upwd ' . $this->params['username'] . ' --password="' . $this->params['password'] . '"';
    }
    $steps[] = 'lando drush cr';
    foreach ($steps as $step) {
      $this->processStep($step, $verbosity);
    }

    # Not technically necessary, but friendly.
    if ($cwd != $this->rootPath) {
      $this->logger->notice('$ cd ' . $cwd);
      chdir($cwd);
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
  private function processStep($step, $verbosity) {
    $this->logger->notice('$ ' . $step);
    $result = '';

    if ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
      if (substr($step, 0, 6) == 'lando ') {
        $v = '';
        if ($verbosity == OutputInterface::VERBOSITY_VERY_VERBOSE) {
          $v = ' -- -v';
        } elseif ($verbosity == OutputInterface::VERBOSITY_DEBUG) {
          $v = ' -- -vvvv';
        }
        $step .= $v;
      }
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
