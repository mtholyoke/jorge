<?php

declare(strict_types = 1);

namespace MountHolyoke\JorgeTests;

use MountHolyoke\Jorge\Jorge;
use MountHolyoke\JorgeTests\RandomStringTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tests basic functionality of starting and loading config against its
 * own codebase. Tests in mock environments with boundary and error
 * conditions are in @todo TBD.
 */
final class JorgeDefaultTest extends TestCase
{
    use RandomStringTrait;

    protected $jorge;

    /**
     * Creates an instance to test.
     */
    protected function setUp(): void
    {
        $this->jorge = new Jorge();
    }

    /**
     * Test basic instantiation.
     *
     * Jorge’s __construct() creates and configures $input and $output before
     * a typical Symfony application because we need them for setup.
     */
    public function testConstruct(): void
    {
        $this->assertInstanceOf(Jorge::class, $this->jorge);
        $output = $this->jorge->getOutput();
        $this->assertInstanceOf(ConsoleOutput::class, $output);
        $this->assertTrue($output->isDecorated());
        $this->assertSame(OutputInterface::VERBOSITY_NORMAL, $output->getVerbosity());
        $this->assertSame(0, $_ENV['SHELL_VERBOSITY']);
    }

    public function testConfigure(): void
    {
        $this->jorge->configure();
        $this->assertSame('Jorge', $this->jorge->getName());
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.(?:\d+|x)/',
            $this->jorge->getVersion()
        );
        $this->assertSame(__DIR__, $this->jorge->getPath('tests', TRUE));
        $this->assertSame('jorge', $this->jorge->getConfig('appType'));
    }

    public function testGetConfig(): void
    {
        $this->jorge->configure();

        $expect = ['appType' => 'jorge'];
        $config = $this->jorge->getConfig();
        $this->assertSame($expect, $config);

        $key = $this->makeRandomString();
        $default = $this->makeRandomString();
        $value = $this->jorge->getConfig($key, $default);
        $this->assertSame($default, $value);
    }

    /**
     * @todo Use a mock for output?
     */
    public function testLog(): void
    {
        $output = new BufferedOutput();
        $this->jorge->configure($output);
        $expect = "[warning] testLog\n";
        $this->jorge->log(LogLevel::WARNING, 'testLog');
        $this->assertSame($expect, $output->fetch());
    }

    public function testRun(): void
    {
        $this->jorge->setAutoExit(FALSE);
        $output = $this->jorge->getOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        $this->assertSame(0, $this->jorge->run());
    }
}
