<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests;

use MountHolyoke\Jorge\Jorge;

class MockJorge {
  private $jorge;
  public function __construct() {
    $this->jorge = new Jorge();
    $this->jorge->configure();
  }

  public function __call($name, $args) {
    return $this->jorge->$name($args);
  }

  public function log($level, $message, array $context=[]) {
    print "{$level}: {$message}\n";
  }
}
