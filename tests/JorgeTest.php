<?php
declare(strict_types = 1);

use MountHolyoke\Jorge\Jorge;
use PHPUnit\Framework\TestCase;

final class JorgeTest extends TestCase {
  public function testJorgeInstantiation(): Jorge {
    $jorge = new Jorge();
    $this->assertInstanceOf(Jorge::class, $jorge);
    return $jorge;
  }
}
