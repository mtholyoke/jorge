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

    # 0 testRequireStarted calls exec('version'); first time should fail.
    $fixtures[] = [
      'status' => 1,
    ];

    # 1a testRequireStarted calls exec('version'); second time should be weird.
    $fixtures[] = [
      'output' => ['v2.71828'],
      'status' => 0,
    ];
    # 1b testRequireStarted calls exec('list')
    $fixtures[] = [
      'output' => ['{}'],
      'status' => 0,
    ];
    # 1c testRequireStarted calls run('start')
    $fixtures[] = ['status' => 0];
    # 1d testRequireStarted calls exec('list')
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

    # 2a testRequireStarted calls exec('version'); this should be valid.
    $fixtures[] = [
      'output' => ['v3.0.0-beta.36'],
      'status' => 0,
    ];
    # 2b testRequireStarted calls exec('list')
    $fixtures[] = [
      'output' => [
        '{',
        '"name": "' . $this->project . '",',
        '"running": false',
        '}',
      ],
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

    # 4 testUpdateStatus calls exec('list') to test disabled tool
    $fixtures[] = [
      'output' => [
        '{',
        '"name": "' . $this->project . '",',
        '"running": false',
        '}',
      ],
      'status' => 0,
    ];

    # 5 testUpdateStatus calls exec('list') to test bad exit code
    $fixtures[] = [
      'output' => [
        '{',
        '"name": "' . $this->project . '",',
        '"running": false',
        '}',
      ],
      'status' => 1,
    ];

    # 6 testUpdateStatus calls exec('list') to test name mismatch
    $fixtures[] = [
      'output' => [
        '{',
        '"name": "' . $this->project . '",',
        '"running": false',
        '}',
      ],
      'status' => 0,
    ];

    # Return the mocked response.
    return $fixtures[self::$sequence++];
  }

  /**
   * Gets the version so we can test requireStarted().
   *
   * @return string
   */
  public function getVersion() {
    return $this->version;
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
    $this->version = $version;
  }
}
