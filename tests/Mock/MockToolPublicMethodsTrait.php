<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Mock;

/**
 * Provides public methods to use for testing.
 */
trait MockToolPublicMethodsTrait {
  /**
   * {@inheritDoc}
   */
  public function applyVerbosity($argv = '') {
    return parent::applyVerbosity($argv);
  }

  /**
   * {@inheritDoc}
   */
  public function disable() {
    return parent::disable();
  }

  /**
   * {@inheritDoc}
   */
  public function enable() {
    return parent::enable();
  }

  /**
   * {@inheritDoc}
   */
  public function exec($argv = '') {
    return parent::exec($argv);
  }

  /**
   * Sets verbosity so we can test different behaviors.
   *
   * This is not in the superclass, which gets its verbosity from the application.
   *
   * @param int $verbosity The verbosity level
   * @return $this
   */
  public function setVerbosity($verbosity) {
    $this->verbosity = $verbosity;
    return $this;
  }
}
