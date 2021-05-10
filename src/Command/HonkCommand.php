<?php

declare(strict_types=1);

namespace MountHolyoke\Jorge\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides a Jorge command that can “honk”.
 *
 * @link https://github.com/mtholyoke/jorge
 *
 * @author Jason Proctor <jproctor@mtholyoke.edu>
 * @copyright 2018 Trustees of Mount Holyoke College
 */
class HonkCommand extends Command
{
    /**
     * Establishes the `honk` command.
     */
    protected function configure(): void
    {
        $this
          ->setName('honk')
          ->setDescription('Honks at you')
          ->setHelp('This command is a simple output test to verify that Jorge is running.')
        ;
    }

    /**
     * Executes the `honk` command.
     *
     * Output includes ^G for a beep.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return null|int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): void {
        $output->writeln('Honk!');
    }
}
