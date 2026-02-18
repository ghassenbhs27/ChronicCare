<?php

namespace App\Command;

use App\Service\ActivityBadgeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:badge:assign-most-active',
    description: 'Calcule l utilisateur le plus actif et attribue le badge avec cadeau.',
)]
class AssignMostActiveBadgeCommand extends Command
{
    public function __construct(private readonly ActivityBadgeService $activityBadgeService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Fenetre d activite en jours', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        if ($days <= 0) {
            $io->error('L option --days doit etre superieure a 0.');
            return Command::INVALID;
        }

        $result = $this->activityBadgeService->assignMostActiveBadge($days);
        $winner = $result['winner'];
        $score = (int) $result['score'];

        if ($winner === null || $score <= 0) {
            $io->warning('Aucun utilisateur actif trouve sur la periode.');
            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Badge attribue a %s (%s) avec un score de %d.',
            $winner->getFullName(),
            (string) $winner->getEmail(),
            $score
        ));
        $io->writeln(sprintf('Cadeau: %s', (string) $winner->getActivityReward()));

        return Command::SUCCESS;
    }
}
