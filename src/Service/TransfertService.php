<?php

namespace App\Service;

use App\Entity\Exemplaire;
use App\Entity\Site;
use App\Entity\Transfert;
use App\Enum\MotifTransfert;
use App\Enum\StatutExemplaire;
use App\Enum\StatutReservation;
use App\Exception\RegleMetierException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class TransfertService
{
    use Transactionnel;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationService $reservations,
    ) {
    }

    /** Rééquilibrage du fonds : on envoie un exemplaire disponible vers l'autre site. */
    public function demander(
        Exemplaire $exemplaire,
        Site $destination,
        MotifTransfert $motif = MotifTransfert::REEQUILIBRAGE,
        ?\DateTimeImmutable $maintenant = null,
    ): Transfert {
        $maintenant ??= new \DateTimeImmutable();

        return $this->enTransaction($this->em, function () use ($exemplaire, $destination, $motif, $maintenant): Transfert {
            $this->em->refresh($exemplaire, LockMode::PESSIMISTIC_WRITE);

            if ($exemplaire->getStatut() !== StatutExemplaire::DISPONIBLE) {
                throw new RegleMetierException('Seul un exemplaire disponible peut être transféré.');
            }
            if ($exemplaire->getSiteActuel() === $destination) {
                throw new RegleMetierException("L'exemplaire est déjà sur ce site.");
            }

            $transfert = (new Transfert())
                ->setExemplaire($exemplaire)
                ->setSiteOrigine($exemplaire->getSiteActuel())
                ->setSiteDestination($destination)
                ->setMotif($motif)
                ->setDateDemande($maintenant);
            $this->em->persist($transfert);
            $exemplaire->setStatut(StatutExemplaire::EN_TRANSIT);

            return $transfert;
        });
    }

    /** Le bibliothécaire du site de destination réceptionne la navette. */
    public function recevoir(Transfert $transfert, ?\DateTimeImmutable $maintenant = null): Transfert
    {
        $maintenant ??= new \DateTimeImmutable();

        return $this->enTransaction($this->em, function () use ($transfert, $maintenant): Transfert {
            $copie = $transfert->getExemplaire();
            $this->em->refresh($copie, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($transfert);

            if ($transfert->getDateReception() !== null) {
                throw new RegleMetierException('Ce transfert a déjà été reçu.');
            }
            if ($copie->getStatut() !== StatutExemplaire::EN_TRANSIT) {
                throw new RegleMetierException("L'exemplaire n'est pas en transit.");
            }

            $transfert->setDateReception($maintenant);
            $copie->setSiteActuel($transfert->getSiteDestination());

            $reservation = $transfert->getReservation();
            $reservationToujoursValable = $reservation !== null
                && $reservation->getStatut() === StatutReservation::EN_ATTENTE
                && $reservation->getExemplaireReserve() === $copie;

            if ($reservationToujoursValable) {
                $this->reservations->mettreDeCote($reservation, $copie, $maintenant);
            } else {
                // Rééquilibrage, ou réservation annulée pendant le trajet.
                $copie->setStatut(StatutExemplaire::DISPONIBLE);
                $this->reservations->attribuerExemplaire($copie, $maintenant);
            }

            return $transfert;
        });
    }
}