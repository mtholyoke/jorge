<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Tool\LandoTool;
use MountHolyoke\JorgeTests\Mock\MockToolPublicMethodsTrait;
use Psr\Log\LogLevel;

/**
 * Supplants the LandoTool class so we can test it.
 */
class MockLandoTool extends LandoTool {
  use MockToolPublicMethodsTrait;

  /** @var string $project The name of the mock project */
  public $project;

  /** @var int $sequence The number of times exec() has been called */
  public static $sequence = 0;

  /** @var string $setVersion The version we want exec to return. */
  public $setVersion;

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

  /**
   * Returns predictable values instead of running Lando.
   */
  protected function exec($argv = '', $prompt = FALSE) {
    # Make the appropriate noises about any call to exec().
    $command = trim($this->getExecutable() . ' ' . $argv);
    if (empty($command)) {
      $this->log(LogLevel::ERROR, 'Cannot execute a blank command');
      return ['command' => '', 'status' => 1];
    }
    $this->log(LogLevel::NOTICE, '$ {%command}', ['%command' => $command]);

    # Establish mocked responses.
    $fixtures = [];

    # testGetVersion calls both setVersion() and exec('version'):
    $fixtures[] = ['output' => [$this->setVersion], 'status' => 0];
    $fixtures[] = ['output' => [],                  'status' => 1];
    $fixtures[] = ['output' => ['v2.71828'],        'status' => 0];
    $fixtures[] = ['output' => [$this->setVersion], 'status' => 0];
    $fixtures[] = ['output' => ['X', 'v3.14.159'],  'status' => 0];
    $fixtures[] = ['output' => [$this->setVersion], 'status' => 0];
    $fixtures[] = ['output' => [$this->setVersion], 'status' => 0];

    # testNeedsAuth calls setVersion() three times.
    $fixtures[] = ['output' => [$this->setVersion], 'status' => 0];
    $fixtures[] = ['output' => [$this->setVersion], 'status' => 0];
    $fixtures[] = ['output' => [$this->setVersion], 'status' => 0];

    # testParseLandoList calls setVersion() three times.
    $fixtures[] = ['output' => [$this->setVersion], 'status' => 0];
    $fixtures[] = ['output' => [$this->setVersion], 'status' => 0];
    $fixtures[] = ['output' => [$this->setVersion], 'status' => 0];

    # 0 testRequireStarted calls exec('list'); first time should fail.
    $fixtures[] = [
      'status' => 1,
    ];

    # 1a testRequireStarted calls exec('list'); not running, weird version
    $fixtures[] = [
      'output' => ['{}'],
      'status' => 0,
    ];
    # 1b testRequireStarted calls run('start')
    $fixtures[] = ['status' => 0];
    # 1c testRequireStarted calls exec('list'); rc.2+ list is expected default
    $fixtures[] = [
      'output' => [
        '{',
        $this->project . ': [',
        '{',
        "key: 'value',",
        'array: [',
        "'element'",
        ']',
        '}',
        ']',
        '}',
      ],
      'status' => 0,
    ];
    # 1d testRequireStarted calls exec('version')
    $fixtures[] = [
      'output' => ['v2.6.9'],
      'status' => 0,
    ];

    # 2a testRequireStarted calls exec('list'); this should be valid.
    $fixtures[] = [
      'output' => [
        '{',
        '"name": "' . $this->project . '",',
        '"running": false',
        '}',
      ],
      'status' => 0,
    ];
    # 2b testRequireStarted calls exec('version')
    $fixtures[] = [
      'output' => ['v3.0.0-beta.36'],
      'status' => 0,
    ];
    # 2c testRequireStarted calls run('start')
    $fixtures[] = ['status' => 0];
    # 2d testRequireStarted calls exec('list')
    $fixtures[] = [
      'output' => [
        '{',
        '"name": "' . $this->project . '",',
        '"running": true',
        '}',
      ],
      'status' => 0,
    ];

    # 3 testRequireStarted calls exec('list') to test when lando is already running
    $fixtures[] = [
      'output' => [
        '{',
        '"name": "' . $this->project . '",',
        '"running": true',
        '}',
      ],
      'status' => 0,
    ];

    # 4 testUpdateStatus calls exec('list') to test bad exit code
    $fixtures[] = [
      'output' => [
        '{',
        '"name": "' . $this->project . '",',
        '"running": false',
        '}',
      ],
      'status' => 1,
    ];

    # 5 testUpdateStatus calls exec('list') and exec('version') to test name mismatch
    $fixtures[] = [
      'output' => [
        '{',
        '"name": "' . $this->project . '",',
        '"running": false',
        '}',
      ],
      'status' => 0,
    ];
    $fixtures[] = [
      'output' => ['v3.0.0-beta.36'],
      'status' => 0,
    ];

    # Return the mocked response.
    return $fixtures[self::$sequence++];
  }

  /**
   * {@inheritDoc}
   */
  public function parseLandoList(array $lines = []) {
    return parent::parseLandoList($lines);
  }

  /**
   * Sets the version so we can test parseLandoList().
   *
   * @param string $version The version this tool reports.
   */
  public function setVersion($version) {
    $this->version = NULL;
    if (!is_null($version)) {
      $this->setVersion = $version;
      $this->getVersion();
    }
  }
}
