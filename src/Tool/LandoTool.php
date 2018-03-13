<?php

namespace MountHolyoke\Jorge\Tool;

use MountHolyoke\Jorge\Tool\Tool;
use Psr\Log\LogLevel;
use Symfony\Component\Yaml\Yaml;

class LandoTool extends Tool {
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

  /**
   * Parse the output from `lando list`, which is not quite JSON.
   *
   * @param array raw output from exec()
   * @return array status objects
   */
  protected function parseLandoList(array $lines = []) {
    # Don’t check the last line
    for ($i = 0; $i < (count($lines) - 1); $i++) {
      if ($lines[$i] == '}') {
        $lines[$i] .= ', ';
      }
    }
    $string = '[' . implode('', $lines) . ']';
    return json_decode($string);
  }

  /**
   * {@inheritdoc}
   */
  public function updateStatus($name = NULL) {
    $exec = $this->exec('list');
    if ($exec['status'] == 0) {
      $list = $this->parseLandoList($exec['output']);
    }
    if (empty($name)) {
      if ($this->isEnabled()) {
        $name = $this->config['name'];
      } else {
        $this->application->log(
          LogLevel::WARNING,
          'No Lando environment configured or specified'
        );
        return;
      }
    }
    $set = FALSE;
    foreach ($list as $status) {
      if ($status->name == $name) {
        $this->setStatus($status);
        $set = TRUE;
      }
    }
    if (!$set) {
      $this->application->log(
        LogLevel::WARNING,
        'Unable to determine status for Lando environment "{%name}"',
        ['%name' => $name]
      );
    }
  }
}
