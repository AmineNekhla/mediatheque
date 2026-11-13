<?php

namespace App\Service;

use App\Entity\Adherent;
use App\Entity\Exemplaire;
use App\Entity\Penalite;
use App\Entity\Pret;
use App\Entity\Site;
use App\Enum\StatutExemplaire;
use App\Enum\StatutPenalite;
use App\Enum\StatutPret;
use App\Enum\StatutReservation;
use App\Enum\TypePenalite;
use App\Exception\RegleMetierException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class PretService
{
    use Transactionnel;

    // Pas de prix de remplacement en base : montants forfaitaires (voir DECISIONS.md).
    public const MONTANT_PERTE_CENTIMES = 10000;  // 100 MAD
    public const MONTANT_ABIME_CENTIMES = 3000;   // 30 MAD

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationService $reservations,
    ) {
    }

    public function emprunter(Adherent $adherent, Exemplaire $exemplaire, ?\DateTimeImmutable $maintenant = null): Pret
    {
        $maintenant ??= new \DateTimeImmutable();

        return $this->enTransaction($this->em, function () use ($adherent, $exemplaire, $maintenant): Pret {
            // Verrou pessimiste : SELECT ... FOR UPDATE. Un second guichet qui vise le même
            // exemplaire attend ici, puis voit le statut à jour (EMPRUNTE) et est refusé.
            $this->em->refresh($exemplaire, LockMode::PESSIMISTIC_WRITE);

            $formule = $adherent->getFormule();

            if (!$adherent->abonnementActifLe($maintenant)) {
                throw new RegleMetierException('Abonnement expiré ou inactif : emprunt impossible.');
            }
            if ($this->compterPenalitesImpayees($adherent) > 0) {
                throw new RegleMetierException('Des pénalités impayées bloquent les emprunts.');
            }
            if ($this->compterPretsActifs($adherent) >= $formule->getPretsMax()) {
                throw new RegleMetierException('Limite de prêts simultanés atteinte pour cette formule.');
            }

            $reservation = null;
            if ($exemplaire->getStatut() === StatutExemplaire::RESERVE) {
                $reservation = $this->reservations->reservationPretePourRetrait($exemplaire);
                if ($reservation === null || $reservation->getAdherent() !== $adherent) {
                    throw new RegleMetierException('Cet exemplaire est réservé pour un autre adhérent.');
                }
            } elseif ($exemplaire->getStatut() !== StatutExemplaire::DISPONIBLE) {
                throw new RegleMetierException(sprintf('Exemplaire indisponible (statut %s).', $exemplaire->getStatut()->value));
            }

            // --- Toutes les vérifications sont passées : on peut modifier. ---
            $reservation?->setStatut(StatutReservation::RETIREE);

            $pret = (new Pret())
                ->setExemplaire($exemplaire)
                ->setAdherent($adherent)
                ->setDateEmprunt($maintenant)
                ->setDateRetourPrevue($maintenant->modify(sprintf('+%d days', $formule->getDureePretJours())))
                ->setStatut(StatutPret::EN_COURS);
            $this->em->persist($pret);
            $exemplaire->setStatut(StatutExemplaire::EMPRUNTE);

            return $pret;
        });
    }

    public function rendre(
        Exemplaire $exemplaire,
        ?Site $siteRetour = null,
        bool $abime = false,
        ?\DateTimeImmutable $maintenant = null,
    ): Pret {
        $maintenant ??= new \DateTimeImmutable();

        return $this->enTransaction($this->em, function () use ($exemplaire, $siteRetour, $abime, $maintenant): Pret {
            $this->em->refresh($exemplaire, LockMode::PESSIMISTIC_WRITE);

            $pret = $this->trouverPretOuvert($exemplaire);
            if ($pret === null) {
                throw new RegleMetierException('Aucun prêt en cours pour cet exemplaire.');
            }

            $pret->setDateRetour($maintenant)->setStatut(StatutPret::RENDU);

            $joursRetard = $this->joursDeRetard($pret->getDateRetourPrevue(), $maintenant);
            if ($joursRetard > 0) {
                $tarif = $pret->getAdherent()->getFormule()->getPenaliteCentimesParJour();
                $this->creerPenalite($pret, TypePenalite::RETARD, $joursRetard * $tarif, $maintenant);
            }

            if ($siteRetour !== null) {
                $exemplaire->setSiteActuel($siteRetour); // rendu dans l'autre médiathèque du réseau
            }

            if ($abime) {
                $exemplaire->setStatut(StatutExemplaire::ABIME);
                $this->creerPenalite($pret, TypePenalite::ABIME, self::MONTANT_ABIME_CENTIMES, $maintenant);
            } else {
                $exemplaire->setStatut(StatutExemplaire::DISPONIBLE);
                $this->reservations->attribuerExemplaire($exemplaire, $maintenant); // sert la file d'attente
            }

            return $pret;
        });
    }

    public function prolonger(Pret $pret, ?\DateTimeImmutable $maintenant = null): Pret
    {
        $maintenant ??= new \DateTimeImmutable();

        return $this->enTransaction($this->em, function () use ($pret, $maintenant): Pret {
            $exemplaire = $pret->getExemplaire();
            $this->em->refresh($exemplaire, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($pret);

            if (!in_array($pret->getStatut(), [StatutPret::EN_COURS, StatutPret::EN_RETARD], true)) {
                throw new RegleMetierException('Ce prêt est déjà clôturé.');
            }
            if ($pret->estEnRetardLe($maintenant)) {
                throw new RegleMetierException('Un prêt en retard ne peut pas être prolongé.');
            }

            $adherent = $pret->getAdherent();
            $formule = $adherent->getFormule();

            if (!$adherent->abonnementActifLe($maintenant)) {
                throw new RegleMetierException('Abonnement expiré ou inactif : prolongation impossible.');
            }
            if ($pret->getNombreProlongations() >= $formule->getProlongationsMax()) {
                throw new RegleMetierException('Nombre maximal de prolongations atteint.');
            }
            if ($this->reservations->desAdherentsAttendent($exemplaire->getEdition())) {
                throw new RegleMetierException('Des adhérents attendent ce document : prolongation impossible.');
            }

            $pret
                ->setDateRetourPrevue($pret->getDateRetourPrevue()->modify(sprintf('+%d days', $formule->getDureePretJours())))
                ->setNombreProlongations($pret->getNombreProlongations() + 1);

            return $pret;
        });
    }

    public function declarerPerdu(Pret $pret, ?\DateTimeImmutable $maintenant = null): Pret
    {
        $maintenant ??= new \DateTimeImmutable();

        return $this->enTransaction($this->em, function () use ($pret, $maintenant): Pret {
            $exemplaire = $pret->getExemplaire();
            $this->em->refresh($exemplaire, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($pret);

            if (!in_array($pret->getStatut(), [StatutPret::EN_COURS, StatutPret::EN_RETARD], true)) {
                throw new RegleMetierException('Ce prêt est déjà clôturé.');
            }

            $pret->setStatut(StatutPret::PERDU);
            $exemplaire->setStatut(StatutExemplaire::PERDU);
            $this->creerPenalite($pret, TypePenalite::PERDU, self::MONTANT_PERTE_CENTIMES, $maintenant);

            return $pret;
        });
    }

    /** Passe en EN_RETARD les prêts non rendus dont l'échéance est dépassée (commande planifiée). */
    public function marquerRetards(?\DateTimeImmutable $maintenant = null): int
    {
        $maintenant ??= new \DateTimeImmutable();

        /** @var list<Pret> $prets */
        $prets = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Pret::class, 'p')
            ->where('p.statut = :statut')
            ->andWhere('p.dateRetourPrevue < :maintenant')
            ->setParameter('statut', StatutPret::EN_COURS->value)
            ->setParameter('maintenant', $maintenant)
            ->getQuery()
            ->getResult();

        foreach ($prets as $pret) {
            $pret->setStatut(StatutPret::EN_RETARD);
        }
        $this->em->flush();

        return count($prets);
    }

    /** Jours calendaires de retard : le jour de l'échéance lui-même n'est pas en retard. */
    private function joursDeRetard(\DateTimeImmutable $prevue, \DateTimeImmutable $maintenant): int
    {
        $echeance = $prevue->setTime(0, 0);
        $jour = $maintenant->setTime(0, 0);

        return $jour <= $echeance ? 0 : (int) $echeance->diff($jour)->days;
    }

    private function creerPenalite(Pret $pret, TypePenalite $type, int $montantCentimes, \DateTimeImmutable $date): void
    {
        $this->em->persist(
            (new Penalite())
                ->setAdherent($pret->getAdherent())
                ->setPret($pret)
                ->setType($type)
                ->setMontantCentimes($montantCentimes)
                ->setStatut(StatutPenalite::IMPAYEE)
                ->setDateCreation($date)
        );
    }

    private function trouverPretOuvert(Exemplaire $exemplaire): ?Pret
    {
        return $this->em->createQueryBuilder()
            ->select('p')
            ->from(Pret::class, 'p')
            ->where('p.exemplaire = :exemplaire')
            ->andWhere('p.statut IN (:statuts)')
            ->setMaxResults(1)
            ->setParameter('exemplaire', $exemplaire)
            ->setParameter('statuts', [StatutPret::EN_COURS->value, StatutPret::EN_RETARD->value])
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function compterPretsActifs(Adherent $adherent): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Pret::class, 'p')
            ->where('p.adherent = :adherent')
            ->andWhere('p.statut IN (:statuts)')
            ->setParameter('adherent', $adherent)
            ->setParameter('statuts', [StatutPret::EN_COURS->value, StatutPret::EN_RETARD->value])
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function compterPenalitesImpayees(Adherent $adherent): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(pe.id)')
            ->from(Penalite::class, 'pe')
            ->where('pe.adherent = :adherent')
            ->andWhere('pe.statut = :statut')
            ->setParameter('adherent', $adherent)
            ->setParameter('statut', StatutPenalite::IMPAYEE->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}