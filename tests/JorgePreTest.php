<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests;

use MountHolyoke\Jorge\Jorge;
use MountHolyoke\JorgeTests\RandomStringTrait;
use PHPUnit\Framework\TestCase;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests Jorge::configure() under a wide variety of conditions.
 */
final class JorgePreTest extends TestCase {
  use RandomStringTrait;

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
      $path .= DIRECTORY_SEPARATOR . $this->makeRandomString();
      mkdir($path);
    }
    chdir($path);
    $this->jorge->configure();
    $this->assertSame($root, $this->jorge->getPath());
  }

  public function testGetPath(): void {
    # Make sure it succeeds if we ask for something exists.
    $root = realpath($this->tempDir->path());
    $randir = $this->makeRandomString();
    $subdir = $root . DIRECTORY_SEPARATOR . $randir;
    mkdir($subdir);
    $this->jorge->configure();
    $this->assertSame($subdir, $this->jorge->getPath($randir));

    # Make sure it fails if we ask for something that doesn’t exist.
    do {
      $baddir = $this->makeRandomString();
    } while (is_dir($root . DIRECTORY_SEPARATOR . $baddir));
    $this->assertNull($this->jorge->getPath($baddir));
    $this->expectException(\DomainException::class);
    $this->jorge->getPath($baddir, TRUE);
  }

  public function testSanitizePath(): void {
    $root = realpath($this->tempDir->path());
    $randir = $this->makeRandomString();
    $subdir = $root . DIRECTORY_SEPARATOR . $randir;
    mkdir($subdir);
    do {
      $baddir = $this->makeRandomString();
    } while (is_dir($root . DIRECTORY_SEPARATOR . $baddir));

    $this->jorge->configure();
    $prefixes = ['/', '../', './..//./ /'];
    foreach ($prefixes as $prefix) {
      $this->assertSame($root, $this->jorge->getPath($prefix));
      $this->assertSame($subdir, $this->jorge->getPath($prefix . $randir));
      $this->assertNull($this->jorge->getPath($prefix . $baddir));
    }
  }

  public function testEmptyConfig(): void {
    $root = realpath($this->tempDir->path());
    $configFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    touch($configFile);
    $this->jorge->configure();
    $this->assertSame([], $this->jorge->getConfig());
  }

  public function testGetConfig(): void {
    $root = realpath($this->tempDir->path());
    $file = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    $key  = $this->makeRandomString();
    $val  = $this->makeRandomString();
    file_put_contents($file, "$key: $val\n");
    $this->jorge->configure();

    # Make sure it retrieves the value.
    $this->assertSame($val, $this->jorge->getConfig($key));
    $this->assertSame($val, $this->jorge->getConfig($key, 'X'));

    # Also make sure it fails correctly.
    do {
      $bad = $this->makeRandomString();
    } while ($bad == $key);
    $this->assertNull($this->jorge->getConfig($bad));
    $this->assertSame('X', $this->jorge->getConfig($bad, 'X'));
  }

  /**
   * Make sure we can supplement with include_config.
   */
  public function testLoadConfigFile_IncludeConfigScalar(): void {
    $root = realpath($this->tempDir->path());

    # Set up initial config file with random values. Include another file.
    $key0 = $this->makeRandomString();
    $key1 = $this->makeRandomString();
    $val1 = $this->makeRandomString();
    $key2 = $this->makeRandomString();
    $val2 = $this->makeRandomString();
    $name = $this->makeRandomString() . '.yml';
    $configYaml = Yaml::dump([
      $key0 => [
        $key1 => $val1,
        $key2 => $val2,
      ],
      'include_config' => $name,
    ]);
    $configFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    file_put_contents($configFile, $configYaml);

    # Set up the included file with different values.
    do {
      $new2 = $this->makeRandomString();    # append to existing inner key 2
    } while ($new2 == $val2);
    do {
      $key3 = $this->makeRandomString();    # supplement inside mainOuterKey
    } while ($key3 == $key1 || $key3 == $key2);
    $val3 = $this->makeRandomString();
    do {
      $key4 = $this->makeRandomString();    # add an additional outer key
    } while ($key4 == $key0);
    $val4 = $this->makeRandomString();
    $includedYaml = Yaml::dump([
      $key0 => [
        $key2 => $new2,
        $key3 => $val3,
      ],
      $key4 => $val4,
    ]);
    $includedFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $name]);
    file_put_contents($includedFile, $includedYaml);

    $this->jorge->configure();

    # Make sure key0 has values from both files.
    $combined = [
      $key1 => $val1,
      $key2 => [$val2, $new2],
      $key3 => $val3,
    ];
    $this->assertSame($combined, $this->jorge->getConfig($key0));

    # Make sure key4 was also included in the config.
    $this->assertSame($val4, $this->jorge->getConfig($key4));
  }

  /**
   * Verify array syntax of include_config for a single inclusion.
   */
  public function testLoadConfigFile_IncludeConfigArraySolo(): void {
    $root = realpath($this->tempDir->path());

    $name = $this->makeRandomString() . '.yml';
    $configYaml = "include_config:\n  - $name\n";
    $configFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    file_put_contents($configFile, $configYaml);

    $key = $this->makeRandomString();
    $val = $this->makeRandomString();
    $includedYaml = "$key: $val\n";
    $includedFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $name]);
    file_put_contents($includedFile, $includedYaml);

    $this->jorge->configure();
    $this->assertSame($val, $this->jorge->getConfig($key));
  }

  /**
   * Verify include_config for multiple inclusion.
   */
  public function testLoadConfigFile_IncludeConfigArrayMulti(): void {
    $root = realpath($this->tempDir->path());

    # Include 2 additional files in config.yml.
    $name1 = $this->makeRandomString() . '.yml';
    do {
      $name2 = $this->makeRandomString() . '.yml';
    } while ($name2 == $name1);
    $configYaml = "include_config:\n  - $name1\n  - $name2\n";
    $configFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    file_put_contents($configFile, $configYaml);

    # Put content in the first file.
    $key1  = $this->makeRandomString();
    $val1  = $this->makeRandomString();
    $yaml1 = "$key1: $val1\n";
    $file1 = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $name1]);
    file_put_contents($file1, $yaml1);

    # Put content in the second file.
    do {
      $key2 = $this->makeRandomString();
    } while ($key2 == $key1);
    $val2  = $this->makeRandomString();
    $yaml2 = "$key2: $val2\n";
    $file2 = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $name2]);
    file_put_contents($file2, $yaml2);

    # Make sure both files’ content is loaded into the config.
    $this->jorge->configure();
    $this->assertSame($val1, $this->jorge->getConfig($key1));
    $this->assertSame($val2, $this->jorge->getConfig($key2));
  }

  /**
   * Verify that nesting include_config does not work.
   *
   * This prevents the need to check for loops.
   */
  public function testLoadConfigFile_IncludeConfigArrayNested(): void {
    $root = realpath($this->tempDir->path());

    # Include an additional config file.
    $name1 = $this->makeRandomString() . '.yml';
    $configYaml = "include_config: $name1\n";
    $configFile = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', 'config.yml']);
    file_put_contents($configFile, $configYaml);

    # Include the second file in the first file.
    $key1 = $this->makeRandomString();
    $val1 = $this->makeRandomString();
    do {
      $name2 = $this->makeRandomString() . '.yml';
    } while ($name2 == $name1);
    $yaml1 = Yaml::dump([
      $key1 => $val1,
      'include_config' => $name2,
    ]);
    $file1 = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $name1]);
    file_put_contents($file1, $yaml1);

    # The second file should never be read.
    do {
      $key2 = $this->makeRandomString();
    } while ($key2 == $key1);
    $val2  = $this->makeRandomString();
    $yaml2 = "$key2: $val2\n";
    $file2 = implode(DIRECTORY_SEPARATOR, [$root, '.jorge', $name2]);
    file_put_contents($file2, $yaml2);

    $this->jorge->configure();

    # The first file gets included, and its include_config is preserved but not parsed.
    $this->assertSame($val1, $this->jorge->getConfig($key1));
    $configFiles = [$name1, $name2];
    $this->assertSame($configFiles, $this->jorge->getConfig('include_config'));
    $this->assertNull($this->jorge->getConfig($key2));
  }
}
