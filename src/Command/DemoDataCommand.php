<?php

namespace App\Command;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo-data',
    description: 'Charge les données de démonstration, uniquement si la base est vide.',
)]
class DemoDataCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Garde-fou : les fixtures vident les tables. On ne les charge jamais sur une base déjà peuplée,
        // sinon un simple redémarrage du conteneur effacerait les données.
        if ($this->em->getRepository(Utilisateur::class)->count([]) > 0) {
            $io->note('La base contient déjà des données : rien n\'est chargé, les données existantes sont conservées.');

            return Command::SUCCESS;
        }

        try {
            $fixtures = $this->getApplication()?->find('doctrine:fixtures:load');
        } catch (CommandNotFoundException) {
            $fixtures = null;
        }

        if ($fixtures === null) {
            $io->error('doctrine:fixtures:load est introuvable dans cet environnement (les fixtures ne sont chargées qu\'avec APP_ENV=dev).');

            return Command::FAILURE;
        }

        $arguments = new ArrayInput([]);
        $arguments->setInteractive(false);

        return $fixtures->run($arguments, $output);
    }
}
