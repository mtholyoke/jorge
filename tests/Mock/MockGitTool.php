<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Tool\GitTool;

/**
 * Supplants the GitTool class so we can test things.
 */
class MockGitTool extends GitTool {
  /**
   * {@inheritDoc}
   */
  public function configure() {
    $this->setName('mockGit');
  }
}
