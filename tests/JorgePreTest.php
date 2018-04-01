<?php
declare(strict_types = 1);

use MountHolyoke\Jorge\Jorge;
use PHPUnit\Framework\TestCase;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Console\Output\OutputInterface;

final class JorgePreTest extends TestCase {
  protected $jorge;
  protected $tempDir;

  protected function setUp() {
    $this->jorge = new Jorge();
    $output = $this->jorge->getOutput();
    $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    $this->tempDir = (new TemporaryDirectory())->create();
  }

  protected function tearDown() {
    $this->tempDir->delete();
  }

  /**
   * Tries configure() when there is no .jorge subdirectory.
   */
  public function testNoDotJorge(): void {
    $root = realpath($this->tempDir->path());
    chdir($root);
    $this->jorge->configure();
    $this->assertNull($this->jorge->getPath());
  }

  /**
   * Tries configure() when .jorge is empty.
   */
  public function testEmptyDotJorge(): void {
    $root = realpath($this->tempDir->path());
    mkdir($root . DIRECTORY_SEPARATOR . '.jorge');
    chdir($root);
    $this->jorge->configure();
    $this->assertSame($root, $this->jorge->getPath());
    $this->assertSame([], $this->jorge->getConfig());
  }


  // TODO: test pathfinding and loading config given various mocks/fixtures:
  // config['includeconfig']
  // static findRootPath()
  // getConfig()
  // getPath()
  // loadConfigFile()
  // static sanitizePath()


}
