<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Command\DrushCommand;
use MountHolyoke\JorgeTests\Mock\MockLogTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Supplants the DrushCommand class so we can test things.
 */
class MockDrushCommand extends DrushCommand {
  use MockLogTrait;

  /**
   * {@inheritDoc}
   */
  public function execute(InputInterface $input, OutputInterface $output) {
    return parent::execute($input, $output);
  }

  /**
   * {@inheritDoc}
   */
  public function findDrupal() {
    return parent::findDrupal();
  }

  /**
   * Returns the current value of $this->drush_command.
   *
   * @return string
   */
  public function getDrushCommand() {
    return $this->drush_command;
  }

  /**
   * {@inheritDoc}
   */
  public function initialize(InputInterface $input, OutputInterface $output) {
    # These are normally done by run() just before initialization:
    $this->mergeApplicationDefinition();
    $input->bind($this->getDefinition());

    return parent::initialize($input, $output);
  }

  /**
   * Tags log messages with command name and passes them to MockLogTrait::mockLog().
   *
   * {@inheritDoc}
   */
  public function log($level, $message, array $context = []) {
    $message = trim('{' . $this->getName() . '} ' . $message);
    $this->mockLog($level, $message, $context);
  }

  /**
   * Allows us to pretend we’ve been through initialization.
   *
   * @param string $drush_command
   * @return $this
   */
  public function setDrushCommand($drush_command) {
    $this->drush_command = $drush_command;
    return $this;
  }

  /**
   * Assigns a Jorge-like object to $this->jorge.
   *
   * @param mixed $jorge An object that will respond to methods we need.
   * @return $this
   */
  public function setJorge($jorge) {
    $this->jorge = $jorge;
    return $this;
  }
}
