<?php

namespace MountHolyoke\Jorge\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HonkCommand extends Command {
  /**
   * Establishes the `honk` command.
   */
  protected function configure() {
    $this
      ->setName('honk')
      ->setDescription('Honks at you')
      ->setHelp('This command is a simple output test to verify that Jorge is running.')
    ;
  }

  /**
   * Executes the `honk` command.
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $output->writeln('Honk!');
  }
}
