<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests;

use MountHolyoke\Jorge\Jorge;
use PHPUnit\Framework\TestCase;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

final class JorgePreTest extends TestCase {
  protected $jorge;
  protected $tempDir;

  protected function setUp(): void {
    $this->jorge = new Jorge();
    $output = $this->jorge->getOutput();
    $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    $this->tempDir = (new TemporaryDirectory())->create();
    mkdir($this->tempDir->path() . DIRECTORY_SEPARATOR . '.jorge');
    chdir($this->tempDir->path());
  }

  protected function tearDown(): void {
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
   * Tries configure() when .jorge is empty.
   */
  public function testEmptyDotJorge(): void {
    $root = realpath($this->tempDir->path());
    $this->jorge->configure();
    $this->assertSame($root, $this->jorge->getPath());
    $this->assertSame([], $this->jorge->getConfig());
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
   * Generate random subdirectory.
   */
  public function testGetPath(): void {
    $root = realpath($this->tempDir->path());
    $randir = bin2hex(random_bytes(4));
    $subdir = $root . DIRECTORY_SEPARATOR . $randir;
    mkdir($subdir);
    $this->jorge->configure();
    $this->assertSame($subdir, $this->jorge->getPath($randir));
    $this->assertNull($this->jorge->getPath($randir . 'x'));
    $this->expectException(\DomainException::class);
    $this->jorge->getPath($randir . 'x', TRUE);
  }

  /**
   * Generate random subdirectory.
   */
  public function testSanitizePath(): void {
    $root = realpath($this->tempDir->path());
    $randir = bin2hex(random_bytes(4));
    $subdir = $root . DIRECTORY_SEPARATOR . $randir;
    mkdir($subdir);
    $this->jorge->configure();
    $prefixes = ['/', '../', './..//./ /'];
    foreach ($prefixes as $prefix) {
      $this->assertSame($root, $this->jorge->getPath($prefix));
      $this->assertSame($subdir, $this->jorge->getPath($prefix . $randir));
      $this->assertNull($this->jorge->getPath($prefix . $randir . 'x'));
    }
  }

  /**
   * Generate empty config file.
   */
  public function testEmptyConfig(): void {
    $root = realpath($this->tempDir->path());
    $configFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    touch($configFile);
    $this->jorge->configure();
    $this->assertSame([], $this->jorge->getConfig());
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

  /**
   * Make sure we can supplement with include_config.
   */
  public function testLoadConfigFile(): void {
    $root = realpath($this->tempDir->path());

    $mainOuterKey = bin2hex(random_bytes(4));
    $mainInnerKey1 = bin2hex(random_bytes(4));
    $mainInnerVal1 = bin2hex(random_bytes(4));
    $mainInnerKey2 = bin2hex(random_bytes(4));
    $mainInnerVal2 = bin2hex(random_bytes(4));
    $newConfigName = bin2hex(random_bytes(4)) . '.yml';
    $mainFileYaml = Yaml::dump([
      $mainOuterKey => [
        $mainInnerKey1 => $mainInnerVal1,
        $mainInnerKey2 => $mainInnerVal2,
      ],
      'include_config' => $newConfigName,
    ]);
    $mainConfigFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    file_put_contents($mainConfigFile, $mainFileYaml);

    do {
      $newInnerVal2 = bin2hex(random_bytes(4));  # append to existing
    } while ($newInnerVal2 == $mainInnerVal2);
    $newInnerKey3 = bin2hex(random_bytes(4));    # supplement inner
    $newInnerVal3 = bin2hex(random_bytes(4));
    $newOuterKey = bin2hex(random_bytes(4));     # supplement outer
    $newOuterVal = bin2hex(random_bytes(4));
    $newFileYaml = Yaml::dump([
      $mainOuterKey => [
        $mainInnerKey2 => $newInnerVal2,
        $newInnerKey3  => $newInnerVal3,
      ],
      $newOuterKey => $newOuterVal,
    ]);
    $newConfigFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $newConfigName]);
    file_put_contents($newConfigFile, $newFileYaml);

    $this->jorge->configure();

    $combined = [
      $mainInnerKey1 => $mainInnerVal1,
      $mainInnerKey2 => [$mainInnerVal2, $newInnerVal2],
      $newInnerKey3  => $newInnerVal3,
    ];
    $this->assertSame($combined, $this->jorge->getConfig($mainOuterKey));
    $this->assertSame($newOuterVal, $this->jorge->getConfig($newOuterKey));
  }
}
