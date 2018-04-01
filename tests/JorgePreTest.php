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
    mkdir($this->tempDir->path() . DIRECTORY_SEPARATOR . '.jorge');
    chdir($this->tempDir->path());
  }

  protected function tearDown() {
    $this->tempDir->delete();
  }

  /**
   * Tries configure() when there is no .jorge subdirectory.
   */
  public function testNoDotJorge(): void {
    $root = realpath($this->tempDir->path());
    rmdir($root . DIRECTORY_SEPARATOR . '.jorge');
    $this->jorge->configure();
    $this->assertNull($this->jorge->getPath());
    $this->expectException(\DomainException::class);
    $this->jorge->getPath('', TRUE);
  }

  /**
   * Finds the project root from a random subdirectory.
   */
  public function testFindRootPath(): void {
    $root = realpath($this->tempDir->path());
    $depth = 2 + random_int(0, 4);
    $path = $root;
    for ($i = 0; $i < $depth; $i++) {
      $path .= DIRECTORY_SEPARATOR . bin2hex(random_bytes(4));
      mkdir($path);
    }
    chdir($path);
    $this->jorge->configure();
    $this->assertSame($root, $this->jorge->getPath());
  }

  /**
   * Finds the project root from a random subdirectory.
   */
  public function testFindRootPath(): void {
    $root = realpath($this->tempDir->path());
    $depth = 2 + random_int(0, 4);
    $path = $root;
    for ($i = 0; $i < $depth; $i++) {
      $path .= DIRECTORY_SEPARATOR . bin2hex(random_bytes(4));
      mkdir($path);
    }
    chdir($path);
    $this->jorge->configure();
    $this->assertSame($root, $this->jorge->getPath());
  }

  /**
   * Tries configure() when .jorge is empty.
   */
  public function testEmptyDotJorge(): void {
    $root = realpath($this->tempDir->path());
    $this->jorge->configure();
    $this->assertSame($root, $this->jorge->getPath());
    $this->assertSame([], $this->jorge->getConfig());
  }

  /**
   * Generate random subdirectory.
   */
  public function testGetPath(): void {
    $root = realpath($this->tempDir->path());
    $randir = bin2hex(random_bytes(4));
    $subdir = $root . DIRECTORY_SEPARATOR . $randir;
    mkdir($subdir);
    $this->jorge->configure();
    $this->assertSame($subdir, $this->jorge->getPath($randir));
    $this->assertSame($root, $this->jorge->getPath($randir . 'x'));
    $this->expectException(\DomainException::class);
    $this->jorge->getPath($randir . 'x', TRUE);
  }

  /**
   * Generate random config file.
   */
  public function testGetConfig(): void {
    $root = realpath($this->tempDir->path());
    $configFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    $configKey = bin2hex(random_bytes(4));
    $configValue = bin2hex(random_bytes(4));
    file_put_contents($configFile, "{$configKey}: {$configValue}\n");
    $this->jorge->configure();
    $this->assertSame($configValue, $this->jorge->getConfig($configKey));
    $this->assertSame($configValue, $this->jorge->getConfig($configKey, 'X'));
    $this->assertNull($this->jorge->getConfig($configKey . 'x'));
    $this->assertSame('X', $this->jorge->getConfig($configKey . 'x', 'X'));
  }

  // TODO: test pathfinding and loading config given various mocks/fixtures:
  // config['includeconfig']
  // loadConfigFile()
  // static sanitizePath()


}
