<?php

namespace App\UI\Console;

use App\Application\DemoData\DemoDataSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:demo-data',
    description: 'Seed demo workspaces, users, sessions, options, votes, and result snapshots.',
)]
final class SeedDemoDataCommand extends Command
{
    public function __construct(
        private readonly DemoDataSeeder $demoDataSeeder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Truncate decision tables before seeding.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->demoDataSeeder->hasAnyData() && !$input->getOption('reset')) {
            $io->error('Database already contains data. Re-run with --reset to seed this demo dataset from scratch.');

            return Command::FAILURE;
        }

        if ($input->getOption('reset')) {
            $this->demoDataSeeder->reset();
        }

        $report = $this->demoDataSeeder->seed();

        $io->success(sprintf(
            'Seeded %d workspaces, %d users, and %d decision sessions.',
            $report->workspaceCount,
            $report->userCount,
            $report->sessionCount,
        ));
        $io->writeln(sprintf('Default demo password for all users: <info>%s</info>', $report->defaultPassword));
        $io->writeln('Run this again with <info>--reset</info> to recreate the dataset from scratch.');

        return Command::SUCCESS;
    }
}
