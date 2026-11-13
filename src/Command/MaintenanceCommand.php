<?php

namespace App\Command;

use App\Service\PretService;
use App\Service\ReservationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:maintenance',
    description: 'Expire les réservations non retirées et marque les prêts en retard (à planifier chaque jour).',
)]
class MaintenanceCommand extends Command
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly PretService $prets,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $expirees = $this->reservations->expirerReservations();
        $retards = $this->prets->marquerRetards();

        $io->success(sprintf('%d réservation(s) expirée(s), %d prêt(s) passé(s) en retard.', $expirees, $retards));

        return Command::SUCCESS;
    }
}