<?php

namespace MountHolyoke\Jorge;

use MountHolyoke\Jorge\Command\DrushCommand;
use MountHolyoke\Jorge\Command\HonkCommand;
use MountHolyoke\Jorge\Command\ResetCommand;
use MountHolyoke\Jorge\Tool\LandoTool;
use MountHolyoke\Jorge\Tool\Tool;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class Jorge extends Application {
  private $config = [];
  private $input;
  private $logger;
  private $output;
  private $rootPath;
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
    $this->setVersion('0.2.1');

    if ($this->rootPath = $this->findRootPath()) {
      $this->config = $this->loadConfigFile('.jorge/config.yml', LogLevel::ERROR);
    }

    // If the config file specifies additional config, load that too.
    if (array_key_exists('include_config', $this->config)) {
      if (!is_array($this->config['include_config'])) {
        $this->config['include_config'] = [ $this->config['include_config'] ];
      }
      foreach ($this->config['include_config'] as $file) {
        $configFile = '.jorge/' . $file;
        $this->logger->debug('Including config file {%filename}', ['%filename' => $configFile]);
        $addition = $this->loadConfigFile($configFile);
        $this->config = array_merge_recursive($this->config, $addition);
      }
    }

    $this->add(new DrushCommand());
    $this->add(new HonkCommand());
    $this->add(new ResetCommand());

    $this->addTool(new LandoTool());
  }

  /**
   * Adds a command-line tool to be used by commands.
   *
   * Adapted from Symfony\Component\Console\Application::add().
   *
   * @param Tool an instance of the tool to add
   * @param string executable command if different from name
   * @return Tool the tool as added
   */
  private function addTool(Tool $tool, $executable = '') {
    $name = $tool->setApplication($this, $executable)->getName();
    if (empty($name)) {
      throw new LogicException(sprintf('The tool defined in "%s" has an invalid or empty name.', get_class($tool)));
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
        $this->logger->notice('Project root: {%root}', ['%root' => $cwd]);
        return $cwd;
      }
      array_pop($wd);
    }
    $this->logger->warning('Can’t find project root');
    return FALSE;
  }

  /**
   * Return a parameter from configuration.
   *
   * @param string $key The key to get from config
   * @param mixed $default The value to return if key not present
   */
  public function getConfig($key, $default = NULL) {
    if (array_key_exists($key, $this->config)) {
      return $this->config[$key];
    }
    return $default;
  }

  /**
   * @return OutputInterface
   */
  public function getOutput() {
    return $this->output;
  }

  /**
   * Get the project root directory, plus an optional subdirectory.
   *
   * Should only be called if the command/tool requires a root path to operate.
   *
   * @param string|NULL subdirectory to test and include
   * @param boolean throw an exception if subdirectory doesn't exist
   * @return string the fully qualified path
   * @throws \DomainException code requies a path but none exists
   */
  public function getPath($subdir = NULL, $required = FALSE) {
    if ($path = $this->rootPath) {
      $subdir = $this->sanitizePath($subdir);
      if (!empty($subdir)) {
        if (is_dir($path . '/' . $subdir)) {
          $path .= '/' . $subdir;
        } else {
          $s = ['%subdir' => $subdir];
          if ($required) {
            throw new \DomainException('Subdirectory "{%subdir}" is required.', $s);
          } else {
            $this->logger->warning('No "{%subdir}" subdirectory in root path', $s);
          }
        }
      }
      return $path;
    } else {
      throw new \DomainException('Project root path is required.');
    }
  }

  /**
   * @param string the name of a tool
   * @return Tool|NULL the tool
   */
  public function getTool($name) {
    if (array_key_exists($name, $this->tools) && !empty($this->tools[$name])) {
      return $this->tools[$name];
    }
    $this->logger->warning('Can’t get tool "{%tool}"', ['%tool' => $name]);
    return NULL;
  }

  /**
   * Loads the contents of a config file.
   *
   * @param string filename relative to project root
   * @param string log level if any messages are generated
   * @return array
   */
  public function loadConfigFile($file, $level = LogLevel::WARNING) {
    $file = $this->sanitizePath($file);
    $pathfile = $this->rootPath . '/' . $file;
    if (is_file($pathfile) && is_readable($pathfile)) {
      // TODO: sanitize values?
      return Yaml::parseFile($pathfile);
    }
    $this->log($level, 'Can’t read config file {%filename}', ['%filename' => $pathfile]);
    return [];
  }

  /**
   * Sends a message to the logger.
   *
   * @param string|NULL what log level to use, or NULL to ignore.
   * @param string the message
   * @param array variable substitutions for the message
   */
   public function log($level, $message, array $context = []) {
     if ($level !== NULL) {
       $this->logger->log($level, $message, $context);
     }
   }

   /**
    * {@inheritdoc}
    */
   public function run(InputInterface $input = NULL, OutputInterface $output = NULL) {
     if (empty($input)) {
       $input = $this->input;
     }
     if (empty($output)) {
       $output = $this->output;
     }
     parent::run($input, $output);
   }

   /**
    * Sanitizes a path or filename so it's safe to use.
    *
    * @param string path to sanitize
    * @return string sanitized path
    */
   public function sanitizePath($path) {
     # Strip leading '/', './', or '../'.
     $path = preg_replace('/^(\/|\.\/|\.\.\/)*/', '', $path);
     // TODO: what else?
     return $path;
   }
}
