<?php

namespace App\Service;

use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Oeuvre;
use App\Entity\Reservation;
use App\Entity\Site;
use App\Entity\Transfert;
use App\Enum\MotifTransfert;
use App\Enum\StatutExemplaire;
use App\Enum\StatutReservation;
use App\Exception\RegleMetierException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class ReservationService
{
    use Transactionnel;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Calendrier $calendrier,
        private readonly int $delaiRetraitJoursOuvres = 7, // paramétrable (DECISIONS.md, question 3)
    ) {
    }

    /** Pose une réservation. Si un exemplaire est déjà disponible dans le réseau, il est affecté tout de suite. */
    public function reserver(
        Adherent $adherent,
        Oeuvre $oeuvre,
        ?Edition $edition,
        Site $siteRetrait,
        ?\DateTimeImmutable $maintenant = null,
    ): Reservation {
        $maintenant ??= new \DateTimeImmutable();

        return $this->enTransaction($this->em, function () use ($adherent, $oeuvre, $edition, $siteRetrait, $maintenant): Reservation {
            if (!$adherent->abonnementActifLe($maintenant)) {
                throw new RegleMetierException('Abonnement expiré ou inactif : réservation impossible.');
            }
            if ($edition !== null && $edition->getOeuvre() !== $oeuvre) {
                throw new RegleMetierException("L'édition choisie n'appartient pas à cette œuvre.");
            }

            $actives = $this->reservationsActives($adherent);
            if (count($actives) >= $adherent->getFormule()->getReservationsMax()) {
                throw new RegleMetierException('Limite de réservations simultanées atteinte.');
            }
            foreach ($actives as $active) {
                if ($active->getOeuvre() === $oeuvre) {
                    throw new RegleMetierException('Vous avez déjà une réservation en cours pour cette œuvre.');
                }
            }

            $reservation = (new Reservation())
                ->setAdherent($adherent)
                ->setOeuvre($oeuvre)
                ->setEdition($edition)
                ->setSiteRetrait($siteRetrait)
                ->setDateCreation($maintenant)
                ->setStatut(StatutReservation::EN_ATTENTE);
            $this->em->persist($reservation);
            $this->em->flush();

            // Un exemplaire est peut-être déjà libre quelque part dans le réseau.
            $copie = $this->trouverExemplaireDisponible($oeuvre, $edition, $siteRetrait);
            if ($copie !== null) {
                $this->em->refresh($copie, LockMode::PESSIMISTIC_WRITE); // relit sous verrou
                if ($copie->getStatut() === StatutExemplaire::DISPONIBLE) {
                    $this->affecter($reservation, $copie, $maintenant);
                }
            }

            return $reservation;
        });
    }

    public function annuler(Reservation $reservation, ?\DateTimeImmutable $maintenant = null): void
    {
        $maintenant ??= new \DateTimeImmutable();

        $this->enTransaction($this->em, function () use ($reservation, $maintenant): void {
            $statut = $reservation->getStatut();
            if (!in_array($statut, [StatutReservation::EN_ATTENTE, StatutReservation::DISPONIBLE], true)) {
                throw new RegleMetierException('Cette réservation ne peut plus être annulée.');
            }

            $copie = $reservation->getExemplaireReserve();
            if ($copie !== null) {
                $this->em->refresh($copie, LockMode::PESSIMISTIC_WRITE);
            }

            $reservation->setStatut(StatutReservation::ANNULEE);

            if ($copie !== null) {
                $reservation->setExemplaireReserve(null);
                if ($statut === StatutReservation::DISPONIBLE) {
                    // L'exemplaire était mis de côté : on le propose au suivant dans la file.
                    $this->liberer($copie, $maintenant);
                }
                // S'il est en transit, TransfertService::recevoir() le libérera à l'arrivée.
            }
        });
    }

    /**
     * Un exemplaire vient d'être libéré (retour, arrivée de navette) :
     * le premier de la file d'attente qui lui correspond est servi.
     * L'exemplaire doit déjà être au statut DISPONIBLE.
     */
    public function attribuerExemplaire(Exemplaire $copie, ?\DateTimeImmutable $maintenant = null): ?Reservation
    {
        $maintenant ??= new \DateTimeImmutable();
        $edition = $copie->getEdition();

        $candidates = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->where('r.oeuvre = :oeuvre')
            ->andWhere('r.statut = :statut')
            ->andWhere('r.exemplaireReserve IS NULL')
            ->andWhere('(r.edition IS NULL OR r.edition = :edition)')
            ->orderBy('r.dateCreation', 'ASC')   // premier arrivé, premier servi
            ->addOrderBy('r.id', 'ASC')          // départage deux réservations simultanées
            ->setMaxResults(1)
            ->setParameter('oeuvre', $edition->getOeuvre())
            ->setParameter('statut', StatutReservation::EN_ATTENTE->value)
            ->setParameter('edition', $edition)
            ->getQuery()
            ->getResult();

        $reservation = $candidates[0] ?? null;
        if ($reservation === null) {
            return null;
        }

        $this->affecter($reservation, $copie, $maintenant);
        $this->em->flush(); // indispensable : la requête suivante doit voir cette affectation

        return $reservation;
    }

    /** L'exemplaire est sur le site de retrait : on le met de côté pour l'adhérent. */
    public function mettreDeCote(Reservation $reservation, Exemplaire $copie, \DateTimeImmutable $maintenant): void
    {
        $copie->setStatut(StatutExemplaire::RESERVE);
        $reservation
            ->setExemplaireReserve($copie)
            ->setStatut(StatutReservation::DISPONIBLE)
            ->setDateExpirationRetrait(
                $this->calendrier->ajouterJoursOuvres($maintenant, $this->delaiRetraitJoursOuvres)
            );
    }

    /** Réservation mise de côté pour cet exemplaire, prête à être retirée. */
    public function reservationPretePourRetrait(Exemplaire $copie): ?Reservation
    {
        return $this->em->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->where('r.exemplaireReserve = :copie')
            ->andWhere('r.statut = :statut')
            ->setMaxResults(1)
            ->setParameter('copie', $copie)
            ->setParameter('statut', StatutReservation::DISPONIBLE->value)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Des adhérents attendent-ils une édition de cette œuvre ? (sert à refuser une prolongation) */
    public function desAdherentsAttendent(Edition $edition): bool
    {
        $nombre = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reservation::class, 'r')
            ->where('r.oeuvre = :oeuvre')
            ->andWhere('r.statut = :statut')
            ->andWhere('r.exemplaireReserve IS NULL')
            ->andWhere('(r.edition IS NULL OR r.edition = :edition)')
            ->setParameter('oeuvre', $edition->getOeuvre())
            ->setParameter('statut', StatutReservation::EN_ATTENTE->value)
            ->setParameter('edition', $edition)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $nombre > 0;
    }

    /** Réservations mises de côté et non retirées à temps : suivant de la file, sinon retour en rayon. */
    public function expirerReservations(?\DateTimeImmutable $maintenant = null): int
    {
        $maintenant ??= new \DateTimeImmutable();

        return $this->enTransaction($this->em, function () use ($maintenant): int {
            /** @var list<Reservation> $expirees */
            $expirees = $this->em->createQueryBuilder()
                ->select('r')
                ->from(Reservation::class, 'r')
                ->where('r.statut = :statut')
                ->andWhere('r.dateExpirationRetrait < :maintenant')
                ->setParameter('statut', StatutReservation::DISPONIBLE->value)
                ->setParameter('maintenant', $maintenant)
                ->getQuery()
                ->getResult();

            foreach ($expirees as $reservation) {
                $copie = $reservation->getExemplaireReserve();
                $reservation->setStatut(StatutReservation::EXPIREE);

                if ($copie !== null) {
                    $this->em->refresh($copie, LockMode::PESSIMISTIC_WRITE);
                    $this->liberer($copie, $maintenant);
                }
            }

            return count($expirees);
        });
    }

    private function liberer(Exemplaire $copie, \DateTimeImmutable $maintenant): void
    {
        $copie->setStatut(StatutExemplaire::DISPONIBLE);
        $this->attribuerExemplaire($copie, $maintenant);
    }

    /** Exemplaire sur le bon site : mis de côté. Sinon : transfert demandé vers le site de retrait. */
    private function affecter(Reservation $reservation, Exemplaire $copie, \DateTimeImmutable $maintenant): void
    {
        $reservation->setExemplaireReserve($copie);

        if ($copie->getSiteActuel() === $reservation->getSiteRetrait()) {
            $this->mettreDeCote($reservation, $copie, $maintenant);

            return;
        }

        $copie->setStatut(StatutExemplaire::EN_TRANSIT);
        $this->em->persist(
            (new Transfert())
                ->setExemplaire($copie)
                ->setSiteOrigine($copie->getSiteActuel())
                ->setSiteDestination($reservation->getSiteRetrait())
                ->setReservation($reservation)
                ->setMotif(MotifTransfert::RESERVATION)
                ->setDateDemande($maintenant)
        );
    }

    /** @return list<Reservation> */
    private function reservationsActives(Adherent $adherent): array
    {
        return $this->em->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->where('r.adherent = :adherent')
            ->andWhere('r.statut IN (:statuts)')
            ->setParameter('adherent', $adherent)
            ->setParameter('statuts', [StatutReservation::EN_ATTENTE->value, StatutReservation::DISPONIBLE->value])
            ->getQuery()
            ->getResult();
    }

    /** Préfère un exemplaire déjà présent sur le site de retrait (pas de transfert nécessaire). */
    private function trouverExemplaireDisponible(Oeuvre $oeuvre, ?Edition $edition, Site $siteRetrait): ?Exemplaire
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Exemplaire::class, 'e')
            ->join('e.edition', 'ed')
            ->where('ed.oeuvre = :oeuvre')
            ->andWhere('e.statut = :statut')
            ->orderBy('e.id', 'ASC')
            ->setParameter('oeuvre', $oeuvre)
            ->setParameter('statut', StatutExemplaire::DISPONIBLE->value);

        if ($edition !== null) {
            $qb->andWhere('ed = :edition')->setParameter('edition', $edition);
        }

        /** @var list<Exemplaire> $copies */
        $copies = $qb->getQuery()->getResult();

        foreach ($copies as $copie) {
            if ($copie->getSiteActuel() === $siteRetrait) {
                return $copie;
            }
        }

        return $copies[0] ?? null;
    }
}