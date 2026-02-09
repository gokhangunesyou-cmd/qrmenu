<?php

namespace App\Command;

use App\Message\CleanOrphanedMediaMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:media:clean-orphaned',
    description: 'Clean soft-deleted media files older than N days from S3 and database',
)]
class CleanOrphanedMediaCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Days since deletion', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');

        $this->bus->dispatch(new CleanOrphanedMediaMessage($days));

        $output->writeln(sprintf('Dispatched cleanup for media deleted more than %d days ago.', $days));

        return Command::SUCCESS;
    }
}
