<?php

namespace App\Tests\Service;

use App\Entity\Exemplaire;
use App\Entity\Oeuvre;
use App\Entity\Site;
use App\Enum\StatutExemplaire;
use App\Enum\StatutPenalite;
use App\Enum\StatutPret;
use App\Enum\StatutReservation;
use App\Enum\TypePenalite;
use App\Tests\BaseTestCase;

class PretServiceTest extends BaseTestCase
{
    private Site $site;
    private Oeuvre $oeuvre;
    private Exemplaire $copie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->site = $this->creerSite();
        [$this->oeuvre, , $this->copie] = $this->creerOeuvreAvecCopie($this->site);
    }

    // ---------- Emprunter ----------

    public function testEmprunterCreeUnPretEnCours(): void
    {
        $adherent = $this->creerAdherent(site: $this->site);

        $pret = $this->prets->emprunter($adherent, $this->copie, $this->maintenant);

        $this->assertSame(StatutPret::EN_COURS, $pret->getStatut());
        $this->assertSame(StatutExemplaire::EMPRUNTE, $this->copie->getStatut());
        $this->assertSame('2026-12-07', $pret->getDateRetourPrevue()->format('Y-m-d')); // +21 jours
    }

    public function testEmprunterRefuseSiAbonnementExpire(): void
    {
        $adherent = $this->creerAdherent(site: $this->site, fin: '2026-10-31');

        $this->assertRefus(fn () => $this->prets->emprunter($adherent, $this->copie, $this->maintenant), 'Abonnement');
        $this->assertSame(StatutExemplaire::DISPONIBLE, $this->copie->getStatut());
    }

    public function testEmprunterRefuseSiPenaliteImpayee(): void
    {
        $adherent = $this->creerAdherent(site: $this->site);
        $this->creerPenalite($adherent, StatutPenalite::IMPAYEE);

        $this->assertRefus(fn () => $this->prets->emprunter($adherent, $this->copie, $this->maintenant), 'pénalités impayées');
    }

    public function testEmprunterAutoriseSiPenaliteDejaPayee(): void
    {
        $adherent = $this->creerAdherent(site: $this->site);
        $this->creerPenalite($adherent, StatutPenalite::PAYEE);

        $pret = $this->prets->emprunter($adherent, $this->copie, $this->maintenant);

        $this->assertSame(StatutPret::EN_COURS, $pret->getStatut());
    }

    public function testEmprunterRefuseSiLimiteDePretsAtteinte(): void
    {
        $adherent = $this->creerAdherent($this->creerFormule(pretsMax: 1), $this->site);
        [, , $autreCopie] = $this->creerOeuvreAvecCopie($this->site);

        $this->prets->emprunter($adherent, $this->copie, $this->maintenant);

        $this->assertRefus(fn () => $this->prets->emprunter($adherent, $autreCopie, $this->maintenant), 'Limite de prêts');
        $this->assertSame(StatutExemplaire::DISPONIBLE, $autreCopie->getStatut());
    }

    public function testUnExemplaireNePeutPasEtrePreteDeuxFois(): void
    {
        $premier = $this->creerAdherent(site: $this->site);
        $second = $this->creerAdherent(site: $this->site);

        $this->prets->emprunter($premier, $this->copie, $this->maintenant);

        $this->assertRefus(fn () => $this->prets->emprunter($second, $this->copie, $this->maintenant), 'indisponible');
        $this->assertSame(1, $this->compterPretsOuverts($this->copie));
    }

    public function testEmprunterRefuseUnExemplairePerdu(): void
    {
        $adherent = $this->creerAdherent(site: $this->site);
        [, , $perdu] = $this->creerOeuvreAvecCopie($this->site, StatutExemplaire::PERDU);

        $this->assertRefus(fn () => $this->prets->emprunter($adherent, $perdu, $this->maintenant), 'indisponible');
    }

    public function testExemplaireMisDeCotePourUnAutreEstRefuse(): void
    {
        $a = $this->creerAdherent(site: $this->site);
        $b = $this->creerAdherent(site: $this->site);
        $c = $this->creerAdherent(site: $this->site);

        $this->prets->emprunter($a, $this->copie, $this->maintenant);
        $this->reservations->reserver($b, $this->oeuvre, null, $this->site, $this->maintenant->modify('+1 hour'));
        $this->prets->rendre($this->copie, null, false, $this->maintenant->modify('+10 days'));

        $this->assertSame(StatutExemplaire::RESERVE, $this->copie->getStatut());
        $this->assertRefus(
            fn () => $this->prets->emprunter($c, $this->copie, $this->maintenant->modify('+11 days')),
            'réservé pour un autre'
        );
    }

    public function testLAdherentQuiAReserveRetireSonExemplaire(): void
    {
        $a = $this->creerAdherent(site: $this->site);
        $b = $this->creerAdherent(site: $this->site);

        $this->prets->emprunter($a, $this->copie, $this->maintenant);
        $reservation = $this->reservations->reserver($b, $this->oeuvre, null, $this->site, $this->maintenant->modify('+1 hour'));
        $this->prets->rendre($this->copie, null, false, $this->maintenant->modify('+10 days'));

        $pret = $this->prets->emprunter($b, $this->copie, $this->maintenant->modify('+11 days'));

        $this->assertSame(StatutPret::EN_COURS, $pret->getStatut());
        $this->assertSame(StatutReservation::RETIREE, $reservation->getStatut());
        $this->assertSame(StatutExemplaire::EMPRUNTE, $this->copie->getStatut());
    }

    // ---------- Rendre ----------

    public function testRendreDansLesTempsNeCreeAucunePenalite(): void
    {
        $adherent = $this->creerAdherent(site: $this->site);
        $this->prets->emprunter($adherent, $this->copie, $this->maintenant);

        $pret = $this->prets->rendre($this->copie, null, false, $this->maintenant->modify('+10 days'));

        $this->assertSame(StatutPret::RENDU, $pret->getStatut());
        $this->assertNotNull($pret->getDateRetour());
        $this->assertSame(StatutExemplaire::DISPONIBLE, $this->copie->getStatut());
        $this->assertCount(0, $this->penalitesDe($adherent));
    }

    public function testRendreEnRetardCreeUnePenaliteProportionnelle(): void
    {
        $adherent = $this->creerAdherent($this->creerFormule(penaliteParJour: 50), $this->site);
        $this->prets->emprunter($adherent, $this->copie, $this->maintenant); // échéance : 7 décembre

        $this->prets->rendre($this->copie, null, false, new \DateTimeImmutable('2026-12-10 10:00'));

        $penalites = $this->penalitesDe($adherent);
        $this->assertCount(1, $penalites);
        $this->assertSame(TypePenalite::RETARD, $penalites[0]->getType());
        $this->assertSame(150, $penalites[0]->getMontantCentimes()); // 3 jours x 50 centimes
        $this->assertSame(StatutPenalite::IMPAYEE, $penalites[0]->getStatut());
    }

    public function testRendreLeJourDeLEcheanceNEstPasEnRetard(): void
    {
        $adherent = $this->creerAdherent(site: $this->site);
        $this->prets->emprunter($adherent, $this->copie, $this->maintenant); // échéance : 7 décembre à 10h

        $this->prets->rendre($this->copie, null, false, new \DateTimeImmutable('2026-12-07 18:00'));

        $this->assertCount(0, $this->penalitesDe($adherent));
    }

    public function testRendreUnExemplaireAbimeLeRetireDeLaCirculation(): void
    {
        $adherent = $this->creerAdherent(site: $this->site);
        $this->prets->emprunter($adherent, $this->copie, $this->maintenant);

        $this->prets->rendre($this->copie, null, true, $this->maintenant->modify('+5 days'));

        $this->assertSame(StatutExemplaire::ABIME, $this->copie->getStatut());
        $penalites = $this->penalitesDe($adherent);
        $this->assertCount(1, $penalites);
        $this->assertSame(TypePenalite::ABIME, $penalites[0]->getType());
        $this->assertSame(3000, $penalites[0]->getMontantCentimes());
    }

    public function testRendreSertLePremierDeLaFileDAttente(): void
    {
        $a = $this->creerAdherent(site: $this->site);
        $b = $this->creerAdherent(site: $this->site);
        $this->prets->emprunter($a, $this->copie, $this->maintenant);
        $reservation = $this->reservations->reserver($b, $this->oeuvre, null, $this->site, $this->maintenant->modify('+1 hour'));
        $this->assertSame(StatutReservation::EN_ATTENTE, $reservation->getStatut());

        $this->prets->rendre($this->copie, null, false, $this->maintenant->modify('+10 days'));

        $this->assertSame(StatutReservation::DISPONIBLE, $reservation->getStatut());
        $this->assertSame(StatutExemplaire::RESERVE, $this->copie->getStatut());
        $this->assertSame($this->copie, $reservation->getExemplaireReserve());
        $this->assertNotNull($reservation->getDateExpirationRetrait());
    }

    public function testRendreSansPretEnCoursEstRefuse(): void
    {
        $this->assertRefus(fn () => $this->prets->rendre($this->copie, null, false, $this->maintenant), 'Aucun prêt');
    }

    public function testRendreDansLAutreMediathequeMetAJourLeSiteActuel(): void
    {
        $autreSite = $this->creerSite();
        $adherent = $this->creerAdherent(site: $this->site);
        $this->prets->emprunter($adherent, $this->copie, $this->maintenant);

        $this->prets->rendre($this->copie, $autreSite, false, $this->maintenant->modify('+3 days'));

        $this->assertSame($autreSite, $this->copie->getSiteActuel());
        $this->assertSame($this->site, $this->copie->getSiteAttache()); // le site d'attache ne change pas
    }

    // ---------- Prolonger ----------

    public function testProlongerRepousseLEcheance(): void
    {
        $adherent = $this->creerAdherent(site: $this->site);
        $pret = $this->prets->emprunter($adherent, $this->copie, $this->maintenant);

        $this->prets->prolonger($pret, $this->maintenant->modify('+5 days'));

        $this->assertSame('2026-12-28', $pret->getDateRetourPrevue()->format('Y-m-d')); // 7 déc. + 21 jours
        $this->assertSame(1, $pret->getNombreProlongations());
    }

    public function testProlongerRefuseSiMaximumAtteint(): void
    {
        $adherent = $this->creerAdherent($this->creerFormule(prolongationsMax: 1), $this->site);
        $pret = $this->prets->emprunter($adherent, $this->copie, $this->maintenant);
        $this->prets->prolonger($pret, $this->maintenant->modify('+5 days'));

        $this->assertRefus(fn () => $this->prets->prolonger($pret, $this->maintenant->modify('+6 days')), 'maximal');
    }

    public function testProlongerRefuseSiLePretEstEnRetard(): void
    {
        $adherent = $this->creerAdherent(site: $this->site);
        $pret = $this->prets->emprunter($adherent, $this->copie, $this->maintenant);

        $this->assertRefus(fn () => $this->prets->prolonger($pret, new \DateTimeImmutable('2026-12-10')), 'en retard');
    }

    public function testProlongerRefuseSiDesAdherentsAttendent(): void
    {
        $a = $this->creerAdherent(site: $this->site);
        $b = $this->creerAdherent(site: $this->site);
        $pret = $this->prets->emprunter($a, $this->copie, $this->maintenant);
        $this->reservations->reserver($b, $this->oeuvre, null, $this->site, $this->maintenant->modify('+1 hour'));

        $this->assertRefus(fn () => $this->prets->prolonger($pret, $this->maintenant->modify('+5 days')), 'attendent');
        $this->assertSame(0, $pret->getNombreProlongations());
    }

    // ---------- Perte et retards ----------

    public function testDeclarerPerduFacturePenaliteForfaitaire(): void
    {
        $adherent = $this->creerAdherent(site: $this->site);
        $pret = $this->prets->emprunter($adherent, $this->copie, $this->maintenant);

        $this->prets->declarerPerdu($pret, $this->maintenant->modify('+30 days'));

        $this->assertSame(StatutPret::PERDU, $pret->getStatut());
        $this->assertSame(StatutExemplaire::PERDU, $this->copie->getStatut());
        $penalites = $this->penalitesDe($adherent);
        $this->assertCount(1, $penalites);
        $this->assertSame(TypePenalite::PERDU, $penalites[0]->getType());
        $this->assertSame(10000, $penalites[0]->getMontantCentimes());
    }

    public function testMarquerRetardsPasseLesPretsEchusEnRetard(): void
    {
        $adherent = $this->creerAdherent(site: $this->site);
        $pret = $this->prets->emprunter($adherent, $this->copie, $this->maintenant);

        $this->assertSame(0, $this->prets->marquerRetards($this->maintenant->modify('+5 days')));
        $this->assertSame(StatutPret::EN_COURS, $pret->getStatut());

        $this->assertSame(1, $this->prets->marquerRetards(new \DateTimeImmutable('2026-12-10')));
        $this->assertSame(StatutPret::EN_RETARD, $pret->getStatut());
    }
}