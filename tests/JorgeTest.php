<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests;

use MountHolyoke\JorgeTests\MockJorge;
use MountHolyoke\Jorge\Jorge;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Console\Output\ConsoleOutput;

final class JorgeTest extends TestCase {
  protected $jorge;
  protected $tempDir;

  /**
   * Creates a Jorge-like object on which we can test output.
   */
  protected function setUp(): void {
    $this->tempDir = (new TemporaryDirectory())->create();
    $root = $this->tempDir->path();
    mkdir($root . DIRECTORY_SEPARATOR . '.jorge');
    chdir($root);
    $this->jorge = new MockJorge($root);
    // $output = $this->jorge->getOutput();
    // $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    $this->jorge->configure();
  }

  protected function tearDown(): void {
    $this->tempDir->delete();
  }

  /**
   * Verify the MockJorge is working as expected
   */
  public function testMockJorge() {
    $this->assertSame('Jorge', $this->jorge->getName());

    $logLevels = [
      LogLevel::EMERGENCY,
      LogLevel::ALERT,
      LogLevel::CRITICAL,
      LogLevel::ERROR,
      LogLevel::WARNING,
      LogLevel::NOTICE,
      LogLevel::INFO,
      LogLevel::DEBUG,
    ];
    foreach ($logLevels as $logLevel) {
      $logString = bin2hex(random_bytes(4));
      $logExpect = [$logLevel, $logString, []];
      $this->jorge->log($logLevel, $logString);
      $this->assertSame($logExpect, end($this->jorge->messages));
    }

    $wlnString = bin2hex(random_bytes(4));
    $wlnExpect = ['writeln', $wlnString];
    $this->jorge->getOutput()->writeln($wlnString);
    $this->assertSame($wlnExpect, end($this->jorge->messages));
  }

  // TODO test post-config functionality
  // getTool()
  // run()
}
