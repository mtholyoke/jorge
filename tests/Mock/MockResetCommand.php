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

  /**
   * {@inheritDoc}
   */
  public function execute(InputInterface $input, OutputInterface $output) {
    return parent::execute($input, $output);
  }

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
   * {@inheritDoc}
   */
  public function interact(InputInterface $input, OutputInterface $output) {
    return parent::interact($input, $output);
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
   * Sets an application type for testing.
   *
   * @param string $appType The application type
   * @return $this
   */
  public function setAppType($appType) {
    $this->appType = $appType;
    return $this;
  }

  /**
   * Assigns a Jorge-like object to $this->jorge.
   *
   * @param mixed $jorge An object that will respond to methods we need
   * @return $this
   */
  public function setJorge($jorge) {
    $this->jorge = $jorge;
    return $this;
  }

  /**
   * Sets parameters for testing.
   *
   * @param array $params New values for params
   * @return $this
   */
  public function setParams($params) {
    $this->params = $params;
    return $this;
  }
}
