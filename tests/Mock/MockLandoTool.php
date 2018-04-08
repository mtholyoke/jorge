<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Tool\LandoTool;
use Psr\Log\LogLevel;

/**
 * Supplants the LandoTool class so we can test it.
 */
class MockLandoTool extends LandoTool {
  public $project;
  public $sequence = 0;

  /**
   * Saves the name it’s passed to use later as a Lando environment.
   */
  public function __construct($project = '') {
    parent::__construct();
    $this->project = $project;
  }

  /**
   * {@inheritDoc}
   */
  public function configure() {
    $this->setName('mockLando');
  }

  public function disable() {
    $this->enabled = FALSE;
  }

  public function enable() {
    $this->enabled = TRUE;
  }

  /**
   * Returns predictable values instead of running Lando.
   */
  protected function exec($argv = '') {
    # Make the appropriate noises about any call to exec().
    $command = trim($this->getExecutable() . ' ' . $argv);
    if (empty($command)) {
      $this->log(LogLevel::ERROR, 'Cannot execute a blank command');
      return ['command' => '', 'status' => 1];
    }
    $this->log(LogLevel::NOTICE, '$ {%command}', ['%command' => $command]);

    # Establish mocked responses.
    $fixtures = [];
    # 0 testRequireStarted calls exec('list')
    $fixtures[] = [
      'output' => [
        '{',
        '"name": "' . $this->project . '",',
        '"location": "' . $this->jorge->getPath() . '",',
        '"running": false',
        '}',
      ],
      'status' => 0,
    ];
    # 1 testRequireStarted calls run('start')
    $fixtures[] = ['status' => 0];
    # 2 testRequireStarted calls exec('list')
    $fixtures[] = [
      'output' => [
        '{',
        '"name": "' . $this->project . '",',
        '"location": "' . $this->jorge->getPath() . '",',
        '"running": true',
        '}',
      ],
      'status' => 0,
    ];
    # 2 testRequireStarted calls exec('list')
    $fixtures[] = [
      'output' => [
        '{',
        '"name": "' . $this->project . '",',
        '"location": "' . $this->jorge->getPath() . '",',
        '"running": true',
        '}',
      ],
      'status' => 0,
    ];

    # Return the mocked response.
    print "\nlando $argv [$this->sequence]";
    return $fixtures[$this->sequence++];
  }
}
