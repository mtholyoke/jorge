<?php

namespace MountHolyoke\Jorge\Tool;

use MountHolyoke\Jorge\Tool\Tool;
use Symfony\Component\Yaml\Yaml;

class LandoTool extends Tool {
  protected $config;

  /**
   * Establishes the `lando` tool.
   */
  protected function configure() {
    $this->setName('lando');
  }

  /**
   * Reads the Lando config file, and enables the tool if config is present.
   */
  protected function initialize() {
    $enable = TRUE;

    if (empty($this->getExecutable())) {
      $enable = FALSE;
    }

    $this->config = $this->application->loadConfigFile('.lando.yml', NULL);
    if (empty($this->config)) {
      $enable = FALSE;
    }

    $this->enabled = $enable;
  }
}
