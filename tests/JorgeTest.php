<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests;

use MountHolyoke\JorgeTests\MockJorge;
use MountHolyoke\Jorge\Jorge;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\ConsoleOutput;

final class JorgeTest extends TestCase {
  protected $jorge;

  /**
   * Creates a Jorge object with its log() methed replaced so we can test output.
   */
   protected function setUp(): void {
     $this->jorge = new MockJorge();
     // $output = $this->jorge->getOutput();
     // $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
     // $this->tempDir = (new TemporaryDirectory())->create();
     // mkdir($this->tempDir->path() . DIRECTORY_SEPARATOR . '.jorge');
     // chdir($this->tempDir->path());
     $this->jorge->configure();
   }

   // protected function tearDown(): void {
   //   $this->tempDir->delete();
   // }

   public function testMockJorge() {
     $this->assertSame('Jorge', $this->jorge->getName());
     $this->expectOutputString("warning: using MockJorge\n");
     $this->jorge->log(LogLevel::WARNING, 'using MockJorge');
   }

  // TODO test log levels

  // TODO test post-config functionality
  // getTool()
  // run()
}
