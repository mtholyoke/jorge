<?php

declare(strict_types=1);

namespace MountHolyoke\Jorge;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extends \Symfony\Component\Console\Application with new functionality.
 *
 * Jorge is used as a fancy shell script—it consolidates common sequences
 * of commands necessary to maintain a local development environment for
 * other applications.
 *
 * @link https://github.com/mtholyoke/jorge
 *
 * @author Jason Proctor <jproctor@mtholyoke.edu>
 * @copyright 2018–2021 Trustees of Mount Holyoke College
 * @version 0.7.x-dev
 */
class Jorge extends Application
{
    /** @var array $config Project configuration from .jorge/config.yml */
    protected $config = [];

    /** @var \Symfony\Component\Console\Input\InputInterface $input */
    private $input;

    /** @var \Symfony\Component\Console\Logger\ConsoleLogger $logger */
    private $logger;

    /** @var \Symfony\Component\Console\Output\OutputInterface $output */
    private $output;

    /**
     * Instantiates the object, including IO objects which would not normally
     * exist until a command was run, so we can provide verbose output.
     */
    public function __construct()
    {
        parent::__construct();
        $this->input = new ArgvInput();
        $this->output = new ConsoleOutput();
        $this->configureIO($this->input, $this->output);
        $this->logger = new ConsoleLogger($this->output);
    }

    /**
     * Reads configuration and adds commands.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     */
    public function configure($output = null): void
    {
        $this->setName('Jorge');
        $this->setVersion('0.7.x-dev');
        if (!is_null($output)) {
            $this->output = $output;
            $this->configureIO($this->input, $output);
            $this->logger = new ConsoleLogger($output);
        }
    }


    /**
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Sends a message to the logger.
     *
     * @param string|null $level   What log level to use, or NULL to ignore
     * @param string      $message May need $context interpolation
     * @param array       $context Variable substitutions for $message
     * @see \Symfony\Component\Console\Logger\ConsoleLogger
     */
    public function log($level, $message, array $context = []): void
    {
        if ($level !== null) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Encapsulates the parent::run() method so we don’t have to expose the
     * instantiated IO interface objects.
     *
     * {@inheritDoc}
     */
    public function run(
        InputInterface $input = null,
        OutputInterface $output = null
    ): int {
        $input = $input ?? $this->input;
        $output = $output ?? $this->output;
        return parent::run($input, $output);
    }
}
