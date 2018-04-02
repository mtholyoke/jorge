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
    $root = realpath($this->tempDir->path());
    mkdir($root . DIRECTORY_SEPARATOR . '.jorge');
    chdir($root);
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
    $this->assertSame($subdir, $this->jorge->getPath("$randir"));
    $this->assertNull($this->jorge->getPath("${randir}x"));
    $this->expectException(\DomainException::class);
    $this->jorge->getPath("${randir}x", TRUE);
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
    file_put_contents($configFile, "x$configKey : x$configValue\n");
    $this->jorge->configure();
    $this->assertSame("x$configValue", $this->jorge->getConfig("x$configKey"));
    $this->assertSame("x$configValue", $this->jorge->getConfig("x$configKey", 'X'));
    $this->assertNull($this->jorge->getConfig("xx$configKey"));
    $this->assertSame('X', $this->jorge->getConfig("xx$configKey", 'X'));
  }

  /**
   * Make sure we can supplement with include_config.
   */
  public function testLoadConfigFile_IncludeConfigScalar(): void {
    $root = realpath($this->tempDir->path());

    # Set up initial config file with random values. Include another file.
    $mainOuterKey = bin2hex(random_bytes(4));
    $mainInnerKey1 = bin2hex(random_bytes(4));
    $mainInnerVal1 = bin2hex(random_bytes(4));
    $mainInnerKey2 = bin2hex(random_bytes(4));
    $mainInnerVal2 = bin2hex(random_bytes(4));
    $newConfigName = bin2hex(random_bytes(4)) . '.yml';
    $mainFileYaml = Yaml::dump([
      "x$mainOuterKey" => [
        "x$mainInnerKey1" => "x$mainInnerVal1",
        "x$mainInnerKey2" => "x$mainInnerVal2",
      ],
      'include_config' => $newConfigName,
    ]);
    $mainConfigFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    file_put_contents($mainConfigFile, $mainFileYaml);

    # Set up the included file with different values.
    do {
      $newInnerVal2 = bin2hex(random_bytes(4));    # append to existing inner key 2
    } while ($newInnerVal2 == $mainInnerVal2);
    do {
      $newInnerKey3 = bin2hex(random_bytes(4));    # supplement inside mainOuterKey
    } while ($newInnerKey3 == $mainInnerKey1 || $newInnerKey3 == $mainInnerKey2);
    $newInnerVal3 = bin2hex(random_bytes(4));
    do {
      $newOuterKey = bin2hex(random_bytes(4));     # add an additional outer key
    } while ($newOuterKey == $mainOuterKey);
    $newOuterVal = bin2hex(random_bytes(4));
    $newFileYaml = Yaml::dump([
      "x$mainOuterKey" => [
        "x$mainInnerKey2" => "x$newInnerVal2",
        "x$newInnerKey3"  => "x$newInnerVal3",
      ],
      "x$newOuterKey" => "x$newOuterVal",
    ]);
    $newConfigFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $newConfigName]);
    file_put_contents($newConfigFile, $newFileYaml);

    $this->jorge->configure();

    $combined = [
      "x$mainInnerKey1" => "x$mainInnerVal1",
      "x$mainInnerKey2" => ["x$mainInnerVal2", "x$newInnerVal2"],
      "x$newInnerKey3"  => "x$newInnerVal3",
    ];
    $this->assertSame($combined, $this->jorge->getConfig("x$mainOuterKey"));
    $this->assertSame("x$newOuterVal", $this->jorge->getConfig("x$newOuterKey"));
  }

  /**
   * Verify alternate syntax of include_config for a single inclusion.
   */
  public function testLoadConfigFile_IncludeConfigArraySolo(): void {
    $root = realpath($this->tempDir->path());
    $newConfigName = bin2hex(random_bytes(4)) . '.yml';
    $mainFileYaml = "include_config:\n  -  $newConfigName\n";
    $mainConfigFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    file_put_contents($mainConfigFile, $mainFileYaml);
    $newOuterKey = bin2hex(random_bytes(4));
    $newOuterVal = bin2hex(random_bytes(4));
    $newFileYaml = "x$newOuterKey : x$newOuterVal\n";
    $newConfigFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $newConfigName]);
    file_put_contents($newConfigFile, $newFileYaml);

    $this->jorge->configure();

    $this->assertSame("x$newOuterVal", $this->jorge->getConfig("x$newOuterKey"));
  }

  /**
   * Verify include_config for multiple inclusion.
   */
  public function testLoadConfigFile_IncludeConfigArrayMulti(): void {
    $root = realpath($this->tempDir->path());

    # Include 2 additional files in config.yml.
    $new1ConfigName = bin2hex(random_bytes(4)) . '.yml';
    do {
      $new2ConfigName = bin2hex(random_bytes(4)) . '.yml';
    } while ($new2ConfigName == $new1ConfigName);
    $mainFileYaml = "include_config:\n  -  $new1ConfigName\n  -  $new2ConfigName\n";
    $mainConfigFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    file_put_contents($mainConfigFile, $mainFileYaml);

    # Put content in the first file.
    $new1OuterKey = bin2hex(random_bytes(4));
    $new1OuterVal = bin2hex(random_bytes(4));
    $new1FileYaml = "x$new1OuterKey : x$new1OuterVal\n";
    $new1ConfigFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $new1ConfigName]);
    file_put_contents($new1ConfigFile, $new1FileYaml);

    # Put content in the second file.
    do {
      $new2OuterKey = bin2hex(random_bytes(4));
    } while ($new2OuterKey == $new1OuterKey);
    $new2OuterVal = bin2hex(random_bytes(4));
    $new2FileYaml = "x$new2OuterKey : x$new2OuterVal\n";
    $new2ConfigFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $new2ConfigName]);
    file_put_contents($new2ConfigFile, $new2FileYaml);

    $this->jorge->configure();

    $this->assertSame("x$new1OuterVal", $this->jorge->getConfig("x$new1OuterKey"));
    $this->assertSame("x$new2OuterVal", $this->jorge->getConfig("x$new2OuterKey"));
  }

  /**
   * Nesting include_config does not work, so we don’t have to check for loops.
   */
  public function testLoadConfigFile_IncludeConfigArrayNested(): void {
    $root = realpath($this->tempDir->path());

    # Include an additional config file.
    $new1ConfigName = bin2hex(random_bytes(4)) . '.yml';
    $mainFileYaml = "include_config:\n  -  $new1ConfigName\n";
    $mainConfigFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    file_put_contents($mainConfigFile, $mainFileYaml);

    # Include the second file in the first file.
    do {
      $new2ConfigName = bin2hex(random_bytes(4)) . '.yml';
    } while ($new2ConfigName == $new1ConfigName);
    $new1OuterKey = bin2hex(random_bytes(4));
    $new1OuterVal = bin2hex(random_bytes(4));
    $new1FileYaml = "x$new1OuterKey : x$new1OuterVal\ninclude_config: $new2ConfigName\n";
    $new1ConfigFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $new1ConfigName]);
    file_put_contents($new1ConfigFile, $new1FileYaml);

    # The second file should never be read.
    do {
      $new2OuterKey = bin2hex(random_bytes(4));
    } while ($new2OuterKey == $new1OuterKey);
    $new2OuterVal = bin2hex(random_bytes(4));
    $new2FileYaml = "x$new2OuterKey : x$new2OuterVal\n";
    $new2ConfigFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $new2ConfigName]);
    file_put_contents($new2ConfigFile, $new2FileYaml);

    $this->jorge->configure();

    # The first file gets included, and its include_config is preserved but not parsed.
    $this->assertSame("x$new1OuterVal", $this->jorge->getConfig("x$new1OuterKey"));
    $configFiles = [$new1ConfigName, $new2ConfigName];
    $this->assertSame($configFiles, $this->jorge->getConfig('include_config'));
    $this->assertNull($this->jorge->getConfig("x$new2OuterKey"));
  }
}
