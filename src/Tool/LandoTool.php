<?php

namespace MountHolyoke\Jorge\Tool;

use MountHolyoke\Jorge\Tool\Tool;
use Symfony\Component\Yaml\Yaml;

class LandoTool extends Tool {
  protected function configure() {
    $this->setName('lando');
  }
}
