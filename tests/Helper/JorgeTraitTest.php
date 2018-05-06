<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Helper;

use MountHolyoke\Jorge\Tool\Tool;
use MountHolyoke\JorgeTests\Mock\MockJorge;
use MountHolyoke\JorgeTests\OutputVerifierTrait;
use MountHolyoke\JorgeTests\RandomStringTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * This class tests parts of JorgeTrait not covered elsewhere.
 *
 * @todo Verify in a Command? Verify generically with a mock?
 */
final class JorgeTraitTest extends TestCase {
  use OutputVerifierTrait;
  use RandomStringTrait;

  /**
   * Verify a Tool’s trait-supplied log() and writeln() methods.
   */
  public function testToolLogAndWriteln(): void {
    $jorge = new MockJorge(realpath(__DIR__ . '/../..'));
    $jorge->configure();
    $jorge->messages = [];

    $echo = $jorge->addTool(new Tool('echo'));
    $text = $this->makeRandomString();
    $code = $echo->runThis($text);
    $this->assertSame(0, $code);

    $expect = [
      [LogLevel::DEBUG,  '{echo} Executable is "{%executable}"'],  # log() in setExecutable()
      [LogLevel::NOTICE, '{echo} $ {%command}'],                   # log() in exec()
      ['writeln',        $text],                                # writeln() in runThis()
    ];
    $this->verifyMessages($expect, $jorge->messages);
  }
}
