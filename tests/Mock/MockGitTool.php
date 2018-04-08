<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Tool\GitTool;
use MountHolyoke\JorgeTests\Mock\MockToolPublicMethodsTrait;

/**
 * Supplants the GitTool class so we can test things.
 */
class MockGitTool extends GitTool {
  use MockToolPublicMethodsTrait;

  public static $rootPath;

  public function __construct($rootPath = '') {
    parent::__construct();
    MockGitTool::$rootPath = $rootPath;

    # initialize() needs a path to check if we’re in a Git repo.
    $this->jorge = new class {
      public function getPath() {
        return MockGitTool::$rootPath;
      }
    };
  }

  /**
   * {@inheritDoc}
   */
  public function configure() {
    $this->setName('mockGit');
    $this->setStatus((object) ['clean' => FALSE]);
  }
}
