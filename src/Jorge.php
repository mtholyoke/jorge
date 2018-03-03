<?php

namespace MountHolyoke;

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use MountHolyoke\Jorge\Command\HonkCommand;

class Jorge extends Application {
  /**
   * Reads configuration and adds commands.
   */
  public function configure() {
    $this->add(new HonkCommand());
  }
}
