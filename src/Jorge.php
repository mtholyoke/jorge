<?php

namespace MountHolyoke\Jorge;

use MountHolyoke\Jorge\Command\HonkCommand;
use MountHolyoke\Jorge\Command\ResetCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Yaml\Yaml;

class Jorge extends Application {
  public $config = [];
  public $input;
  public $logger;
  public $output;
  public $rootPath;

  public function __construct() {
    parent::__construct();
    $this->input = new ArgvInput();
    $this->output = new ConsoleOutput();
    $this->configureIO($this->input, $this->output);
    $this->logger = new ConsoleLogger($this->output);
  }
  /**
   * Reads configuration and adds commands.
   */
  public function configure() {
    // TODO: figure out what else we need to set for a nice interface.
    $this->setName('Jorge');
    $this->setVersion('0.0.1');

    if ($this->rootPath = $this->findRootPath()) {
      $this->config = $this->loadConfigFile('config.yml');
    }

    // If the config file specifies additional config, load that too.
    if (array_key_exists('include_config', $this->config)) {
      if (!is_array($this->config['include_config'])) {
        $this->config['include_config'] = [ $this->config['include_config'] ];
      }
      foreach ($this->config['include_config'] as $configFile) {
        $this->logger->debug('Including config file ' . $configFile);
        $this->config = array_merge_recursive($this->config, $this->loadConfigFile($configFile));
      }
    }

    $this->add(new HonkCommand());
    $this->add(new ResetCommand());
  }

  /**
   * Traverses up the directory tree from current location until it finds the
   * project root, defined as a directory that contains a .jorge directory.
   * Returns FALSE if none found.
   *
   * @return string|FALSE
   */
  private function findRootPath() {
    $wd = explode('/', getcwd());
    while (!empty($wd) && $cwd = implode('/', $wd)) {
      $path = $cwd . '/.jorge';
      if (is_dir($path) && is_readable($path)) {
        $this->logger->notice("Project root: '$cwd'");
        return $cwd;
      }
      array_pop($wd);
    }
    $this->logger->warning("Can’t find project root");
    return FALSE;
  }

  private function loadConfigFile($file) {
    // TODO: sanitize filename!
    $pathfile = $this->rootPath . '/.jorge/' . $file;
    if (is_file($pathfile) && is_readable($pathfile)) {
      // TODO: sanitize values, too!
      return Yaml::parseFile($pathfile);
    }
    return [];
  }
}
