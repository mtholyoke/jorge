<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Tool\LandoTool;

/**
 * Supplants the LandoTool class so we can test it.
 */
class MockLandoTool extends LandoTool {
  /**
   * {@inheritDoc}
   */
  public function configure(): void {
    $this->setName('mockLando');
  }
}
