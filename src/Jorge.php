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
use Symfony\Component\Yaml\Yaml;

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
     *     Optional replacement for the output interface
     */
    public function configure(?OutputInterface $output = null): void
    {
        $this->setName('Jorge');
        $this->setVersion('0.7.x-dev');
        if (!is_null($output)) {
            $this->output = $output;
            $this->configureIO($this->input, $output);
            $this->logger = new ConsoleLogger($output);
        }

        // Find and load configuration.
        $this->rootPath = $this->findRootPath();
        if (!is_null($this->rootPath)) {
            $this->log(
                LogLevel::NOTICE,
                'Project root: {%root}',
                ['%root' => $this->rootPath]
            );
            $this->config = $this->loadConfigFile(
                '.jorge' . DIRECTORY_SEPARATOR . 'config.yml',
                LogLevel::ERROR
            );
        } else {
            $this->log(LogLevel::WARNING, 'Can’t find project root');
        }
    }

    /**
     * Traverses up the directory tree from current location until it finds the
     * project root, defined as a directory that contains a .jorge directory.
     *
     * @return string|null full path to document root, or null if none found
     */
    private static function findRootPath(): ?string
    {
        $wd = explode(DIRECTORY_SEPARATOR, getcwd());
        while (!empty($wd) && $cwd = implode(DIRECTORY_SEPARATOR, $wd)) {
            $path = $cwd . DIRECTORY_SEPARATOR . '.jorge';
            if (is_dir($path) && is_readable($path)) {
                return $cwd;
            }
            array_pop($wd);
        }
        return null;
    }

    /**
     * Return a parameter from configuration.
     *
     * @param string|null $key     The key to get from config, NULL for all
     * @param mixed       $default The value to return if key not present
     */
    public function getConfig(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->config;
        }
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }
        return $default;
    }

    /**
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    /**
     * Return a complete path to the specified subdirectory of the project root.
     *
     * Only call this if the command/tool requires a root path to operate.
     *
     * @param string|null $subdir
     *     Subdirectory to include in the path if it exists
     * @param boolean     $required
     *     Throw an exception if subdirectory doesn't exist
     * @return string|null
     * @throws \DomainException if code requies a path but none exists
     */
    public function getPath(
        ?string $subdir = null,
        bool $required = false
    ): ?string {
        $path = $this->rootPath;
        if (is_null($path) && $required) {
            throw new \DomainException('Project root path is required.');
        }

        $subdir = $this->sanitizePath($subdir);
        if (empty($subdir)) {
            return $path;
        }

        $path = $path . DIRECTORY_SEPARATOR . $subdir;
        if (is_dir($path)) {
            return $path;
        }

        if ($required) {
            throw new \DomainException(
                'Subdirectory "' . $subdir . '" is required.'
            );
        }

        $this->log(
            LogLevel::WARNING,
            'No "{%subdir}" subdirectory in root path',
            ['%subdir' => $subdir]
        );
        return null;
    }

    /**
     * Loads the contents of a config file from the project root.
     *
     * @param string $file  Filename relative to project root
     * @param string $level Log level if any messages are generated
     * @return array
     *
     * @todo Sanitize values before returning?
     */
    public function loadConfigFile(
        string $file,
        string $level = LogLevel::WARNING
    ): array {
        $file = $this->sanitizePath($file);
        $pathfile = $this->rootPath . DIRECTORY_SEPARATOR . $file;
        if (!is_file($pathfile) || !is_readable($pathfile)) {
            $this->log(
                $level,
                'Can’t read config file {%filename}',
                ['%filename' => $pathfile]
            );
            return [];
        }
        $extension = pathinfo($pathfile, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'yaml':
            case 'yml':
                $parsed = Yaml::parseFile($pathfile) ?: [];
                break;
            case 'json':
                $parsed = json_decode(file_get_contents($pathfile));
                break;
            default:
                $parsed = file_get_contents($pathfile);
        }
        return $parsed;
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

    /**
     * Sanitizes a path or filename so it’s safe to use.
     *
     * @param string $path The path to sanitize
     * @return string
     *
     * @todo Are any other sanitization steps necessary?
     */
    protected static function sanitizePath(string $path): string
    {
        $path = trim($path);

        # Strip leading '/', './', or '../'.
        $ds = (DIRECTORY_SEPARATOR == '#') ? '\#' : DIRECTORY_SEPARATOR;
        $path = preg_replace('#^(\.{0,2}' . $ds . '\s*)*#', '', $path);

        return $path;
    }
}
