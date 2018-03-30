<?php

namespace MountHolyoke\Jorge;

use MountHolyoke\Jorge\Command\DrushCommand;
use MountHolyoke\Jorge\Command\HonkCommand;
use MountHolyoke\Jorge\Command\ResetCommand;
use MountHolyoke\Jorge\Tool\ComposerTool;
use MountHolyoke\Jorge\Tool\GitTool;
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

/**
 * Extends \Symfony\Component\Console\Application with new functionality.
 *
 * Jorge is used as a fancy shell script—it consolidates common sequences
 * of commands necessary to maintain a local development environment for
 * other applications.
 *
 * @link https://github.com/mtholyoke/jorge
 *
 * @author Jason Proctor <jproctor@mtholyoke.edu>
 * @copyright 2018 Trustees of Mount Holyoke College
 * @version 0.3.1
 */
class Jorge extends Application {
  /** @var array $config Project configuration from .jorge/config.yml */
  private $config = [];

  /** @var \Symfony\Component\Console\Input\InputInterface $input */
  private $input;

  /** @var \Symfony\Component\Console\Logger\ConsoleLogger $logger */
  private $logger;

  /** @var \Symfony\Component\Console\Output\OutputInterface $output */
  private $output;

  /** @var string $rootPath The fully qualified path of the project root */
  private $rootPath;

  /** @var array $tools The instances of Tool\Tool available to this app. */
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
    $this->setVersion('0.3.1');

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

    $this->addTool(new ComposerTool());
    $this->addTool(new GitTool());
    $this->addTool(new LandoTool());
  }

  /**
   * Adds a Tool\Tool representation of a command-line tool.
   *
   * Adapted from \Symfony\Component\Console\Application::add().
   *
   * @param Tool\Tool $tool       An instance of the tool to add
   * @param string    $executable Command if different from name
   * @return Tool\Tool
   */
  public function addTool(Tool $tool, $executable = '') {
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
   * @return string|false full path to document root, or FALSE if none found
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
   * @param string $key     The key to get from config
   * @param mixed  $default The value to return if key not present
   */
  public function getConfig($key, $default = NULL) {
    if (array_key_exists($key, $this->config)) {
      return $this->config[$key];
    }
    return $default;
  }

  /**
   * @return \Symfony\Component\Console\Output\OutputInterface
   */
  public function getOutput() {
    return $this->output;
  }

  /**
   * Return a complete path to the specified subdirectory of the project root.
   *
   * Should only be called if the command/tool requires a root path to operate.
   *
   * @param string|null $subdir   Subdirectory to include in the path if it exists
   * @param boolean     $required Throw an exception if subdirectory doesn't exist
   * @return string
   * @throws \DomainException if code requies a path but none exists
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
   * @param string $name The name of a tool
   * @return Tool\Tool|null
   */
  public function getTool($name) {
    if (array_key_exists($name, $this->tools) && !empty($this->tools[$name])) {
      return $this->tools[$name];
    }
    $this->logger->warning('Can’t get tool "{%tool}"', ['%tool' => $name]);
    return NULL;
  }

  /**
   * Loads the contents of a config file from the project root.
   *
   * @param string $file  Filename relative to project root
   * @param string $level Log level if any messages are generated
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
   * @param string|null $level   What log level to use, or NULL to ignore
   * @param string      $message May need $context interpolation
   * @param array       $context Variable substitutions for $message
   * @see Symfony\Component\Console\Logger\ConsoleLogger
   */
  public function log($level, $message, array $context = []) {
    if ($level !== NULL) {
      $this->logger->log($level, $message, $context);
    }
  }

   /**
    * Encapsulates the parent::run() method so we don’t have to expose the
    * instantiated IO interface objects.
    *
    * {@inheritDoc}
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
    * Sanitizes a path or filename so it’s safe to use.
    *
    * @param string $path The path to sanitize
    * @return string
    */
  protected function sanitizePath($path) {
    # Strip leading '/', './', or '../'.
    $path = preg_replace('/^(\/|\.\/|\.\.\/)*/', '', $path);
    // TODO: what else?
    return $path;
  }
}
