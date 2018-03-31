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
   * Sets up a minimal with no .jorge subdirectory
   */
  public function testNoDotJorge(): void {
    $root = $this->tempDir->path();
    chdir($root);
    $this->jorge->configure();
  }


  // TODO: test pathfinding and loading config given various mocks/fixtures:
  // config['includeconfig']
  // static findRootPath()
  // getConfig()
  // getPath()
  // loadConfigFile()
  // static sanitizePath()


}
