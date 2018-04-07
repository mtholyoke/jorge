<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Helper;

use MountHolyoke\Jorge\Tool\Tool;
use MountHolyoke\JorgeTests\Mock\MockJorge;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * This class tests parts of JorgeTrait not covered elsewhere.
 *
 * @todo Verify in a Command? Verify generically with a mock?
 */
final class JorgeTraitTest extends TestCase {
  /**
   * Verify a Tool’s trait-supplied log() and writeln() methods.
   */
  public function testToolLogAndWriteln(): void {
    $jorge = new MockJorge(realpath(__DIR__ . '/../..'));
    $jorge->configure();
    $jorge->messages = [];

    $echo = $jorge->addTool(new Tool('echo'));
    $text = bin2hex(random_bytes(4));
    $code = $echo->runThis("x$text");
    $this->assertSame(0, $code);

    $addAndRun = [
      [LogLevel::DEBUG,  '{echo} Executable is "{%executable}"'],  # log() in setExecutable()
      [LogLevel::NOTICE, '{echo} $ {%command}'],                   # log() in exec()
      ['writeln',        "x$text"],                                # writeln() in runThis()
    ];
    $this->assertSame(count($addAndRun), count($jorge->messages));
    foreach ($addAndRun as $expected) {
      $actual = array_shift($jorge->messages);
      # Strip interpolation context: paths may be different on different computers.
      if ($actual[0] != 'writeln') {
        array_pop($actual);
      }
      $this->assertSame($expected, $actual);
    }
    $this->assertSame(0, count($jorge->messages));
  }
}
