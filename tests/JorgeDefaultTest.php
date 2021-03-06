<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests;

use MountHolyoke\Jorge\Jorge;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class JorgeDefaultTest extends TestCase {
  protected $jorge;

  protected function setUp() {
    $this->jorge = new Jorge();
  }

  /**
   * Test basic instantiation.
   *
   * Jorge’s __construct() creates and configures $input and $output before
   * a typical Symfony application because we need them for setup.
   */
  public function testConstruct(): void {
    $this->assertInstanceOf(Jorge::class, $this->jorge);
    $output = $this->jorge->getOutput();
    $this->assertInstanceOf(ConsoleOutput::class, $output);
    $this->assertTrue($output->isDecorated());
    $this->assertSame(OutputInterface::VERBOSITY_NORMAL, $output->getVerbosity());
    $this->assertSame(0, $_ENV['SHELL_VERBOSITY']);
  }

  /**
   * Test default configuration.
   */
  public function testConfigure(): void {
    $defaultCommands = $this->jorge->all();
    $defaultTools = $this->jorge->allTools() ?: [];
    $this->jorge->configure();

    $this->assertSame('Jorge', $this->jorge->getName());
    $this->assertRegExp('/\d\.\d\.\d/', $this->jorge->getVersion());
    $this->assertSame(__DIR__, $this->jorge->getPath('tests', TRUE));
    $this->assertSame('jorge', $this->jorge->getConfig('appType'));

    # Commands and Tools are added.
    $this->assertGreaterThan(count($defaultCommands), count($this->jorge->all()));
    $this->assertGreaterThan(count($defaultTools), count($this->jorge->allTools()));
  }

  /**
   * Make sure Jorge runs without errors.
   */
  public function testRun(): void {
    $this->jorge->configure();
    $this->jorge->setAutoExit(FALSE);
    $output = $this->jorge->getOutput();
    $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
    $this->assertSame(0, $this->jorge->run());
  }
}
