<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Helper\ComposerApplication;
use MountHolyoke\Jorge\Tool\ComposerTool;
use MountHolyoke\JorgeTests\Mock\MockToolPublicMethodsTrait;
use PHPUnit\Framework\TestCase;

/**
 * Supplants the ComposerTool class so we can replace its Composer.
 */
class MockComposerTool extends ComposerTool {
  use MockToolPublicMethodsTrait;

  /**
   * {@inheritDoc}
   */
  public function argvJoin(array $argv = []) {
    return parent::argvJoin($argv);
  }

  /**
   * {@inheritDoc}
   */
  public function configure() {
    $this->setName('mockComposer');
  }

  /**
   * Replaces the Composer being used to run commands with a mock.
   *
   * @param \PHPUnit\Framework\TestCase $test The current TestCase
   * @return $this
   */
  public function mockComposerApplication($test) {
    # exec() needs $this->jorge->getOutput() to work, but it gets passed
    # to the mocked run() below without analysis.
    $this->jorge = new class {
      public function getOutput() {
        return NULL;
      }
    };

    $mock = $test->getMockBuilder(ComposerApplication::class)
                 ->setMethods(['run'])
                 ->getMock();

    $mock->method('run')->willReturn(0);

    $this->composerApplication = $mock;
    return $this;
  }

  /**
   * Sets config for loadConfigFile and in a composer.json file.
   *
   * @param string $project The project name
   */
  public function stubConfig($project) {
    $config = ['name' => $project];
    $this->stubJorge['loadConfigFile'] = $config;
    $this->stubJorge['loadConfigFileWarning'] = FALSE;
    file_put_contents('composer.json', json_encode($config));
  }
}
