<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests;

/**
 * Provides utility methods for comparing expected output to actual.
 */
trait OutputVerifierTrait {
  /**
   * Pops off the interpolation values from a log() message.
   */
  private static function filterContext($message) {
    if (count($message) == 3) {
      array_pop($message);
    }
    return $message;
  }

  /**
   * Compares a single message.
   *
   * @param array   $expect     The expected message
   * @param array   $actual     The actual message
   * @param boolean $useContext Verify values provided for interpolation
   */
  public function verifyMessage($expect, $actual, $useContext = FALSE) {
    if (!$useContext) {
      $expect = $this->filterContext($expect);
      $actual = $this->filterContext($actual);
    }
    $this->assertSame($expect, $actual);
  }

  /**
   * Compares an array of messages.
   *
   * @param array   $expect     The expected messages
   * @param array   $actual     The actual messages
   * @param boolean $useContext Verify values provided for interpolation
   */
  public function verifyMessages($expect, $actual, $useContext = FALSE) {
    $this->assertSame(count($expect), count($actual));
    foreach ($expect as $e) {
      $a = array_shift($actual);
      $this->verifyMessage($e, $a, $useContext);
    }
  }
}
