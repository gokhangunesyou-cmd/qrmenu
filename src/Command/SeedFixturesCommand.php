<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;

#[AsCommand(
    name: 'app:fixtures:seed',
    description: 'Load initial seed data (roles, themes, PDF templates, locales, super admin)',
)]
class SeedFixturesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = $this->getApplication();

        if ($application === null) {
            $output->writeln('Application not available.');
            return Command::FAILURE;
        }

        $command = $application->find('doctrine:fixtures:load');
        $arguments = new ArrayInput([
            '--append' => true,
        ]);

        return $command->run($arguments, $output);
    }
}
