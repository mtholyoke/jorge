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

  public function __construct() {
    parent::__construct();
    $this->stubJorge();
  }

  /**
   * {@inheritDoc}
   */
  public function configure() {
    $this->setName('mockGit');
    $this->setStatus((object) ['clean' => FALSE]);
  }
}
