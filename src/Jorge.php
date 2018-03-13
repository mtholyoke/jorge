<?php

namespace MountHolyoke\Jorge;

use MountHolyoke\Jorge\Command\HonkCommand;
use MountHolyoke\Jorge\Command\ResetCommand;
use MountHolyoke\Jorge\Tool\LandoTool;
use MountHolyoke\Jorge\Tool\Tool;
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
  private $tools;

  /**
   * Instantiates the object, including IO objects which would not normally
   * exist until a command was run, so we can provide verbose output.
   */
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
    $this->setName('Jorge');
    $this->setVersion('0.1.0');

    if ($this->rootPath = $this->findRootPath()) {
      $this->config = $this->loadConfigFile('.jorge/config.yml');
    }

    // If the config file specifies additional config, load that too.
    if (array_key_exists('include_config', $this->config)) {
      if (!is_array($this->config['include_config'])) {
        $this->config['include_config'] = [ $this->config['include_config'] ];
      }
      foreach ($this->config['include_config'] as $configFile) {
        $this->logger->debug('Including config file .jorge/' . $configFile);
        $this->config = array_merge_recursive($this->config, $this->loadConfigFile('.jorge/' . $configFile));
      }
    }

    $this->add(new HonkCommand());
    $this->add(new ResetCommand());

    $this->addTool(new LandoTool());
  }

  private function addTool(Tool $tool) {
    $tool->setApplication($this);
    if (!($name = $tool->getName())) {
      throw new LogicException(sprintf('The tool defined in "%s" cannot have an empty name.', get_class($tool)));
    }
    $this->tools[$name] = $tool;
    return $tool;
  }

  /**
   * Traverses up the directory tree from current location until it finds the
   * project root, defined as a directory that contains a .jorge directory.
   *
   * @return string|FALSE full path to document root, or FALSE if none found
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
    $this->logger->warning('Can’t find project root');
    return FALSE;
  }

  /**
   * Loads the contents of a config file.
   *
   * @param string filename relative to project root
   * @return array
   */
  public function loadConfigFile($file) {
    # Strip leading '/', './', or '../'.
    $file = preg_replace('/^(\/|\.\/|\.\.\/)*/', '', $file);
    $pathfile = $this->rootPath . '/' . $file;
    if (is_file($pathfile) && is_readable($pathfile)) {
      // TODO: sanitize values?
      return Yaml::parseFile($pathfile);
    } else {
      $this->logger->warning('Can’t read config file ' . $pathfile);
    }
    return [];
  }
}
