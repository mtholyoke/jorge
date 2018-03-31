<?php
declare(strict_types = 1);

use MountHolyoke\Jorge\Jorge;
use PHPUnit\Framework\TestCase;

final class JorgeTest extends TestCase {
  protected $jorge;

  /**
   * @todo Mocked IO
   */
  protected function setUp() {
    $this->jorge = new Jorge();
    $this->jorge->configure();
  }

  // TODO test log

  // TODO test post-config functionality
  // getTool()
  // run()
}
