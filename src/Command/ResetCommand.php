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

/**
 * Provides a Jorge command that can reset a development environment to a clean state.
 *
 * For Drupal 8, it assumes Git, Composer, and Lando for a Pantheon-hosted site.
 *
 * @link https://github.com/mtholyoke/jorge
 *
 * @author Jason Proctor <jproctor@mtholyoke.edu>
 * @copyright 2018 Trustees of Mount Holyoke College
 */
class ResetCommand extends Command {
  use JorgeTrait;

  /** @var string $appType Type of project being reset */
  protected $appType;

  /** @var array $params Specifies the desired end state of the reset */
  protected $params;

  /**
   * Establishes the `reset` command with its command-line options and default parameters.
   */
  protected function configure() {
    $this
      ->setName('reset')
      ->setDescription('Aligns code, database, and files to a specified state')
      ->addOption('auth',     'a', InputOption::VALUE_OPTIONAL, 'Terminus machine token to use')
      ->addOption('branch',   'b', InputOption::VALUE_OPTIONAL, 'Git branch to use <fg=yellow>[default: "master"]</>')
      ->addOption('content',  'c', InputOption::VALUE_OPTIONAL, 'Environment to load database and files from <fg=yellow>[default: "dev"]</>')
      ->addOption('database', 'd', InputOption::VALUE_OPTIONAL, 'Environment to load database from <fg=yellow>[default: "dev"]</>')
      ->addOption('files',    'f', InputOption::VALUE_OPTIONAL, 'Environment to copy files from <fg=yellow>[default: "dev"]</>')
      ->addOption('username', 'u', InputOption::VALUE_OPTIONAL, 'Admin account to have local password set')
      ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Local password for admin account')
      ->setHelp('This command updates the local git environment to the latest master, copies the latest database and files from the specified environment on Pantheon, and imports the default config suitable for a hands-on development instance.')
    ;

    # These can be set by config.yml, and set or overridden by command line options
    $this->params = [
      'auth'     => '',
      'branch'   => 'master',
      'content'  => 'dev',
      'rsync'    => TRUE,
      'username' => '',
      'password' => '',
    ];
  }

  /**
   * Processes config and command-line options to set parameters.
   *
   * @uses \MountHolyoke\Jorge\Helper\JorgeTrait::initializeJorge()
   *
   * @param \Symfony\Component\Console\Input\InputInterface   $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->initializeJorge();
    $this->appType = $this->jorge->getConfig('appType', '');
    $config = $this->jorge->getConfig('reset', []);
    foreach (array_keys($this->params) as $var) {
      if (array_key_exists($var, $config)) {
        $this->params[$var] = $config[$var];
      }
      if ($input->hasOption($var) && $input->getOption($var)) {
        $this->params[$var] = $input->getOption($var);
      }
    }

    // Specifying a database overrides specified or default content source.
    // Specifying a file source overrides whichever of those is in effect.
    $this->params['database'] = $this->params['content'];
    $this->params['files']    = $this->params['content'];
    if ($input->hasOption('database') && $input->getOption('database')) {
      $this->params['database'] = $input->getOption('database');
      $this->params['files'] =    $input->getOption('database');
    }
    if ($input->hasOption('files') && $input->getOption('files')) {
      $this->params['files'] = $input->getOption('files');
    }

    $this->log(LogLevel::DEBUG, 'Parameters:');
    foreach (array_keys($this->params) as $var) {
      $this->log(LogLevel::DEBUG, sprintf("  %-8s => '%s'", $var, $this->params[$var]));
    }
  }

  /**
   * Interacts with the user.
   *
   * Prompts the user if an admin account is specified in the parameters without
   * a password. If the user does not provide one, the password won’t be reset.
   *
   * @param \Symfony\Component\Console\Input\InputInterface   $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
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
   *
   * Selects a function which will execute the desired series of actions.
   *
   * @param \Symfony\Component\Console\Input\InputInterface   $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return null|int
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    switch ($this->appType) {
      case 'drupal7':
        return $this->executeDrupal7();
        break;
      case 'drupal8':
        return $this->executeDrupal8();
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
    return 1;
  }

  /**
   * Defines and runs the sequence necessary to reset a Drupal 7 site.
   *
   * Assumes Git and Lando for a Pantheon-hosted site.
   * @todo Implement tools for Git
   * @todo Construct a real return value
   * @todo Refactor into a DDL?
   *
   * @return null|int
   */
  protected function executeDrupal7() {
    $git = $this->jorge->getTool('git');
    $lando = $this->jorge->getTool('lando');

    # Do some stuff in the project root
    chdir($this->jorge->getPath());
    if (!$git->getStatus()->clean) {
      $this->log(LogLevel::ERROR, "Working directory not clean. Aborting.");
      return 1;
    }
    $git->run(['checkout', $this->params['branch']]);
    $git->run(['pull']);
    $lando->requireStarted();
    $lando_pull = 'pull --code=none --database=' . $this->params['database'] . ' --files=' . $this->params['files'];
    if ($this->params['rsync']) {
      $lando_pull .= ' --rsync';
    }
    if ($lando->needsAuth()) {
      if (empty($this->params['auth'])) {
        $this->log(LogLevel::ERROR, "This version of Lando requires an auth token to pull. Aborting.");
        return 1;
      }
      $lando_pull .= ' --auth=' . $this->params['auth'];
    }
    $lando->run($lando_pull);

    $drushSequence = [['drush_command' => ['cc', 'all']]];
    if (!empty($this->params['username']) && !empty($this->params['password'])) {
      $drushSequence[] = [
        'drush_command' => [
          'upwd',
          $this->params['username'],
          '--password="' . $this->params['password'] . '"',
        ],
      ];
      $drushSequence[] = ['drush_command' => ['cc', 'all']];
    }

    $drush = $this->jorge->find('drush');
    foreach ($drushSequence as $step) {
      $drushInput = new ArrayInput($step);
      $drush->run($drushInput, $this->jorge->getOutput());
    }
  }

  /**
   * Defines and runs the sequence necessary to reset a Drupal 8 site.
   *
   * Assumes Git, Composer, and Lando for a Pantheon-hosted site.
   * @todo Construct a real return value
   * @todo Refactor into a DDL?
   *
   * @return null|int
   */
  protected function executeDrupal8() {
    $composer = $this->jorge->getTool('composer');
    $git = $this->jorge->getTool('git');
    $lando = $this->jorge->getTool('lando');

    # Do some stuff in the project root
    chdir($this->jorge->getPath());
    if (!$git->getStatus()->clean) {
      $this->log(LogLevel::ERROR, "Working directory not clean. Aborting.");
      return 1;
    }
    $git->run(['checkout', $this->params['branch']]);
    $git->run(['pull']);
    $composer->run(['command' => 'install']);
    $lando->requireStarted();
    $lando_pull = 'pull --code=none --database=' . $this->params['database'] . ' --files=' . $this->params['files'];
    if ($this->params['rsync']) {
      $lando_pull .= ' --rsync';
    }
    if ($lando->needsAuth()) {
      if (empty($this->params['auth'])) {
        $this->log(LogLevel::ERROR, "This version of Lando requires an auth token to pull. Aborting.");
        return 1;
      }
      $lando_pull .= ' --auth=' . $this->params['auth'];
    }
    $lando->run($lando_pull);

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

    $drush = $this->jorge->find('drush');
    foreach ($drushSequence as $step) {
      $drushInput = new ArrayInput($step);
      $drush->run($drushInput, $this->jorge->getOutput());
    }
  }
}
