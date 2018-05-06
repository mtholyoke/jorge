<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

use MountHolyoke\Jorge\Command\ResetCommand;
use MountHolyoke\JorgeTests\Mock\MockLogTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Supplants the ResetCommand class so we can test things.
 */
class MockResetCommand extends ResetCommand {
  use MockLogTrait;

  public function getAppType() {
    return $this->appType;
  }

  public function getParams() {
    return $this->params;
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
}
