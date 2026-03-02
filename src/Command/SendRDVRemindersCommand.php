<?php

namespace App\Command;

use App\Repository\RDVRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:rdv:send-reminders',
    description: 'Envoie les rappels de rendez-vous Ã  venir.'
)]
class SendRDVRemindersCommand extends Command
{
    public function __construct(
        private readonly RDVRepository $rdvRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();
        $windowEnd = $now->modify('+24 hours');

        $rdvs = $this->rdvRepository->findUpcomingForReminder($now, $windowEnd);
        foreach ($rdvs as $rdv) {
            $this->notificationService->sendRDVReminder($rdv);
            $rdv->setReminderSentAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        $output->writeln(sprintf('Rappels envoyÃ©s: %d', count($rdvs)));
        return Command::SUCCESS;
    }
}
