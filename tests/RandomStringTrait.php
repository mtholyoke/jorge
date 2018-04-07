<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests;

/**
 * Provides utility method for creating random strings.
 */
trait RandomStringTrait {
  /**
   * Creates a random string.
   *
   * @param int $min Minimum number of bytes, must be >= 1
   * @param int $max Maximum number of bytes, must be >= $min
   */
  public function makeRandomString($min = 4, $max = 8) {
    $min  = ($min >= 1) ? $min : 1;
    $max  = ($max >= $min) ? $max : $min;
    $size = rand($min, $max);
    $hex  = bin2hex(random_bytes($size));
    return "x$hex";
  }
}
