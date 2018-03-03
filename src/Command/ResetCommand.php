<?php

namespace MountHolyoke\Jorge\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetCommand extends Command {
  // Useful properties of the Jorge application object
  protected $rootPath;
  protected $logger;
  // Config parameters necessary for this command
  protected $appType;
  protected $params;

  /**
   * Establishes the `reset` command and updates config if necessary.
   */
  protected function configure() {
    $this
      ->setName('reset')
      ->setDescription('Aligns code, database, and files to a specified state')
      ->setHelp('This command updates the local git environment to the latest master, copies the latest database and files from {site}.dev environment on Pantheon, and imports the default config for a hands-on development instance.')
    ;

    // Defaults can be overridden by config.yml
    $this->params = [
      'branch'   => 'master',
      'database' => 'dev',
      'files'    => 'dev',
      'rsync'    => TRUE,
      'password' => 'password',
    ];
  }

  /**
   * Prepares the `reset` command.
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
      }
    }

    $this->logger->debug('Parameters for reset:');
    foreach (array_keys($this->params) as $var) {
      $this->logger->debug(sprintf("  %-8s => '%s'", $var, $this->params[$var]));
    }
  }

  /**
   * Executes the `reset` command.
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $cwd = getcwd();
    switch ($this->appType) {
      case 'drupal8':
        $this->execute_drupal8($cwd);
        break;
      case 'jorge':
        $this->logger->warning('Can’t reset self');
        break;
      case '':
        $this->logger->error('No application type specified');
        break;
      default:
        $this->logger->error('Unrecognized application type "' . $this->appType . '"');
    }
  }

  protected function execute_drupal8($cwd) {
    $this->logger->notice('Resetting Drupal 8 application');
    if ($cwd != $this->rootPath) {
      $this->logger->debug('  cd ' . $this->rootPath);
    }
    $this->logger->debug('  git checkout ' . $this->params['branch']);
    $this->logger->debug('  git pull');
    $this->logger->debug('  composer install');
    $this->logger->debug('  lando pull --code=none --database=' . $this->params['database'] . ' --files=' . $this->params['files'] . ($this->params['rsync'] ? ' --rsync' : ''));
    $this->logger->debug('  cd web');
    $this->logger->debug('  lando drush csim config_dev --yes');
    $this->logger->debug('  lando drush updb --yes');
    $this->logger->debug('  lando drush upwd Administrator --password="' . $this->params['password'] . '"');
    $this->logger->debug('  lando drush cr');
    if ($cwd != $this->rootPath) {
      $this->logger->debug('  cd ' . $cwd);
    }
  }
}
