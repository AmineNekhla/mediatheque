<?php

namespace App\Tests;

use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\FormuleAbonnement;
use App\Entity\Oeuvre;
use App\Entity\Penalite;
use App\Entity\Pret;
use App\Entity\Site;
use App\Enum\StatutExemplaire;
use App\Enum\StatutPenalite;
use App\Enum\StatutPret;
use App\Enum\TypeDocument;
use App\Enum\TypePenalite;
use App\Exception\RegleMetierException;
use App\Service\Calendrier;
use App\Service\PretService;
use App\Service\ReservationService;
use App\Service\TransfertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class BaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;
    protected Calendrier $calendrier;
    protected ReservationService $reservations;
    protected PretService $prets;
    protected TransfertService $transferts;

    /** Date fixe pour des tests déterministes : lundi 16 novembre 2026, 10h. */
    protected \DateTimeImmutable $maintenant;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Les services sont instanciés à la main : c'est explicite et ça évite
        // de dépendre de la visibilité des services dans le conteneur de test.
        $this->calendrier = new Calendrier();
        $this->reservations = new ReservationService($this->em, $this->calendrier);
        $this->prets = new PretService($this->em, $this->reservations);
        $this->transferts = new TransfertService($this->em, $this->reservations);

        $this->maintenant = new \DateTimeImmutable('2026-11-16 10:00:00');
    }

    // ---------- Constructeurs de données ----------

    protected function creerSite(?string $nom = null): Site
    {
        $site = (new Site())->setNom($nom ?? 'Site ' . uniqid())->setAdresse('Casablanca');
        $this->em->persist($site);
        $this->em->flush();

        return $site;
    }

    protected function creerFormule(
        int $pretsMax = 3,
        int $dureePretJours = 21,
        int $prolongationsMax = 1,
        int $reservationsMax = 2,
        int $penaliteParJour = 50,
    ): FormuleAbonnement {
        $formule = (new FormuleAbonnement())
            ->setNom('Formule ' . uniqid())
            ->setPretsMax($pretsMax)
            ->setDureePretJours($dureePretJours)
            ->setProlongationsMax($prolongationsMax)
            ->setReservationsMax($reservationsMax)
            ->setPenaliteCentimesParJour($penaliteParJour)
            ->setPrixCentimes(0);
        $this->em->persist($formule);
        $this->em->flush();

        return $formule;
    }

    protected function creerAdherent(
        ?FormuleAbonnement $formule = null,
        ?Site $site = null,
        string $debut = '2026-01-01',
        string $fin = '2026-12-31',
    ): Adherent {
        $adherent = (new Adherent())
            ->setNumeroCarte('T' . uniqid())
            ->setNom('Nom')
            ->setPrenom('Prenom')
            ->setFormule($formule ?? $this->creerFormule())
            ->setSite($site ?? $this->creerSite())
            ->setDebutAbonnement(new \DateTimeImmutable($debut))
            ->setFinAbonnement(new \DateTimeImmutable($fin));
        $this->em->persist($adherent);
        $this->em->flush();

        return $adherent;
    }

    protected function creerOeuvre(string $titre = 'Oeuvre de test'): Oeuvre
    {
        $oeuvre = (new Oeuvre())->setTitre($titre);
        $this->em->persist($oeuvre);
        $this->em->flush();

        return $oeuvre;
    }

    protected function creerEdition(Oeuvre $oeuvre, TypeDocument $type = TypeDocument::LIVRE): Edition
    {
        $edition = (new Edition())->setOeuvre($oeuvre)->setTypeDocument($type);
        $this->em->persist($edition);
        $this->em->flush();

        return $edition;
    }

    protected function creerExemplaire(
        Edition $edition,
        Site $site,
        StatutExemplaire $statut = StatutExemplaire::DISPONIBLE,
    ): Exemplaire {
        $copie = (new Exemplaire())
            ->setEdition($edition)
            ->setSiteAttache($site)
            ->setSiteActuel($site)
            ->setCodeBarres('B' . uniqid())
            ->setStatut($statut);
        $this->em->persist($copie);
        $this->em->flush();

        return $copie;
    }

    /** @return array{0: Oeuvre, 1: Edition, 2: Exemplaire} */
    protected function creerOeuvreAvecCopie(Site $site, StatutExemplaire $statut = StatutExemplaire::DISPONIBLE): array
    {
        $oeuvre = $this->creerOeuvre();
        $edition = $this->creerEdition($oeuvre);

        return [$oeuvre, $edition, $this->creerExemplaire($edition, $site, $statut)];
    }

    /** Une pénalité (avec un prêt déjà rendu) pour tester les blocages. */
    protected function creerPenalite(Adherent $adherent, StatutPenalite $statut = StatutPenalite::IMPAYEE): Penalite
    {
        [, , $copie] = $this->creerOeuvreAvecCopie($adherent->getSite());

        $pret = (new Pret())
            ->setExemplaire($copie)
            ->setAdherent($adherent)
            ->setDateEmprunt(new \DateTimeImmutable('2026-09-01'))
            ->setDateRetourPrevue(new \DateTimeImmutable('2026-09-22'))
            ->setDateRetour(new \DateTimeImmutable('2026-09-25'))
            ->setStatut(StatutPret::RENDU);
        $this->em->persist($pret);

        $penalite = (new Penalite())
            ->setAdherent($adherent)
            ->setPret($pret)
            ->setType(TypePenalite::RETARD)
            ->setMontantCentimes(150)
            ->setStatut($statut)
            ->setDateCreation(new \DateTimeImmutable('2026-09-25'));
        $this->em->persist($penalite);
        $this->em->flush();

        return $penalite;
    }

    // ---------- Aides aux assertions ----------

    /** Vérifie qu'une règle métier refuse l'opération, avec le bon message. */
    protected function assertRefus(callable $operation, string $extraitMessage): void
    {
        try {
            $operation();
        } catch (RegleMetierException $e) {
            $this->assertStringContainsString($extraitMessage, $e->getMessage());

            return;
        }

        $this->fail('Une RegleMetierException était attendue.');
    }

    protected function compterPretsOuverts(Exemplaire $copie): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Pret::class, 'p')
            ->where('p.exemplaire = :copie')
            ->andWhere('p.statut IN (:statuts)')
            ->setParameter('copie', $copie)
            ->setParameter('statuts', [StatutPret::EN_COURS->value, StatutPret::EN_RETARD->value])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Penalite> */
    protected function penalitesDe(Adherent $adherent): array
    {
        return $this->em->getRepository(Penalite::class)->findBy(['adherent' => $adherent]);
    }
}