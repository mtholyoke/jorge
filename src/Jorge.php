<?php

namespace MountHolyoke;

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Yaml\Yaml;
use MountHolyoke\Jorge\Command\HonkCommand;

class Jorge extends Application {
  public $config;

  /**
   * Reads configuration and adds commands.
   */
  public function configure() {
    // TODO: figure out what else we need to set for a nice interface.
    $this->setName('Jorge');
    $this->setVersion('0.0.1');

    $configPath = $this->findConfigPath();
    $this->config = $this->loadConfigFile($configPath, 'config.yml');

    $this->add(new HonkCommand());
  }

  private function findConfigPath() {
    $wd = explode('/', getcwd());
    while (!empty($wd) && $cwd = implode('/', $wd)) {
      $path = $cwd . '/.jorge';
      if (is_dir($path) && is_readable($path)) {
        return $path;
      }
      array_pop($wd);
    }
    // TODO: Warn if we can't find config?
    return getcwd();
  }

  private function loadConfigFile($path, $file) {
    $pathfile = $path . '/' . $file;
    if (is_file($pathfile) && is_readable($pathfile)) {
      return Yaml::parseFile($pathfile);
    }
    return [];
  }
}
