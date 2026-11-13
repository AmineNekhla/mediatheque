<?php

namespace App\Tests\Service;

use App\Entity\Transfert;
use App\Enum\MotifTransfert;
use App\Enum\StatutExemplaire;
use App\Enum\StatutReservation;
use App\Tests\BaseTestCase;

class ReservationServiceTest extends BaseTestCase
{
    public function testReserverSansExemplaireDisponibleMetEnAttente(): void
    {
        $site = $this->creerSite();
        $oeuvre = $this->creerOeuvre();
        $adherent = $this->creerAdherent(site: $site);

        $reservation = $this->reservations->reserver($adherent, $oeuvre, null, $site, $this->maintenant);

        $this->assertSame(StatutReservation::EN_ATTENTE, $reservation->getStatut());
        $this->assertNull($reservation->getExemplaireReserve());
        $this->assertNull($reservation->getDateExpirationRetrait());
    }

    public function testReserverAvecExemplaireSurLeSiteDeRetraitLeMetDeCote(): void
    {
        $site = $this->creerSite();
        [$oeuvre, , $copie] = $this->creerOeuvreAvecCopie($site);
        $adherent = $this->creerAdherent(site: $site);

        $reservation = $this->reservations->reserver($adherent, $oeuvre, null, $site, $this->maintenant);

        $this->assertSame(StatutReservation::DISPONIBLE, $reservation->getStatut());
        $this->assertSame(StatutExemplaire::RESERVE, $copie->getStatut());
        $this->assertSame($copie, $reservation->getExemplaireReserve());
        $this->assertSame('2026-11-25', $reservation->getDateExpirationRetrait()->format('Y-m-d')); // 7 jours ouvrés
    }

    public function testReserverUnExemplaireDeLAutreSiteDemandeUnTransfert(): void
    {
        $siteRetrait = $this->creerSite();
        $autreSite = $this->creerSite();
        [$oeuvre, , $copie] = $this->creerOeuvreAvecCopie($autreSite);
        $adherent = $this->creerAdherent(site: $siteRetrait);

        $reservation = $this->reservations->reserver($adherent, $oeuvre, null, $siteRetrait, $this->maintenant);

        $this->assertSame(StatutReservation::EN_ATTENTE, $reservation->getStatut());
        $this->assertSame(StatutExemplaire::EN_TRANSIT, $copie->getStatut());

        $transfert = $this->em->getRepository(Transfert::class)->findOneBy(['exemplaire' => $copie]);
        $this->assertNotNull($transfert);
        $this->assertSame($autreSite, $transfert->getSiteOrigine());
        $this->assertSame($siteRetrait, $transfert->getSiteDestination());
        $this->assertSame(MotifTransfert::RESERVATION, $transfert->getMotif());
    }

    public function testReserverRefuseSiLimiteAtteinte(): void
    {
        $site = $this->creerSite();
        $adherent = $this->creerAdherent($this->creerFormule(reservationsMax: 1), $site);

        $this->reservations->reserver($adherent, $this->creerOeuvre('Premier'), null, $site, $this->maintenant);

        $this->assertRefus(
            fn () => $this->reservations->reserver($adherent, $this->creerOeuvre('Second'), null, $site, $this->maintenant),
            'Limite de réservations'
        );
    }

    public function testReserverDeuxFoisLaMemeOeuvreEstRefuse(): void
    {
        $site = $this->creerSite();
        $oeuvre = $this->creerOeuvre();
        $adherent = $this->creerAdherent(site: $site);

        $this->reservations->reserver($adherent, $oeuvre, null, $site, $this->maintenant);

        $this->assertRefus(
            fn () => $this->reservations->reserver($adherent, $oeuvre, null, $site, $this->maintenant),
            'déjà une réservation'
        );
    }

    public function testReserverRefuseSiAbonnementExpire(): void
    {
        $site = $this->creerSite();
        $adherent = $this->creerAdherent(site: $site, fin: '2026-10-31');

        $this->assertRefus(
            fn () => $this->reservations->reserver($adherent, $this->creerOeuvre(), null, $site, $this->maintenant),
            'Abonnement'
        );
    }

    public function testReserverRefuseUneEditionD_uneAutreOeuvre(): void
    {
        $site = $this->creerSite();
        $adherent = $this->creerAdherent(site: $site);
        $edition = $this->creerEdition($this->creerOeuvre('Autre'));

        $this->assertRefus(
            fn () => $this->reservations->reserver($adherent, $this->creerOeuvre('Voulue'), $edition, $site, $this->maintenant),
            "n'appartient pas"
        );
    }

    public function testLaFileEstPremierArrivePremierServi(): void
    {
        $site = $this->creerSite();
        [$oeuvre, , $copie] = $this->creerOeuvreAvecCopie($site);
        $emprunteur = $this->creerAdherent(site: $site);
        $a = $this->creerAdherent(site: $site);
        $b = $this->creerAdherent(site: $site);

        $this->prets->emprunter($emprunteur, $copie, $this->maintenant);
        $resA = $this->reservations->reserver($a, $oeuvre, null, $site, $this->maintenant->modify('+1 hour'));
        $resB = $this->reservations->reserver($b, $oeuvre, null, $site, $this->maintenant->modify('+2 hours'));

        $this->prets->rendre($copie, null, false, $this->maintenant->modify('+10 days'));

        $this->assertSame(StatutReservation::DISPONIBLE, $resA->getStatut());
        $this->assertSame(StatutReservation::EN_ATTENTE, $resB->getStatut());
    }

    public function testUneReservationVisantUneEditionNeRecoitPasUneAutreEdition(): void
    {
        $site = $this->creerSite();
        $oeuvre = $this->creerOeuvre();
        $edition1 = $this->creerEdition($oeuvre);
        $edition2 = $this->creerEdition($oeuvre);
        $copieEdition2 = $this->creerExemplaire($edition2, $site);
        $emprunteur = $this->creerAdherent(site: $site);
        $a = $this->creerAdherent(site: $site);
        $b = $this->creerAdherent(site: $site);

        $this->prets->emprunter($emprunteur, $copieEdition2, $this->maintenant);
        $resA = $this->reservations->reserver($a, $oeuvre, $edition1, $site, $this->maintenant->modify('+1 hour')); // édition 1 exigée
        $resB = $this->reservations->reserver($b, $oeuvre, null, $site, $this->maintenant->modify('+2 hours'));     // n'importe laquelle

        $this->prets->rendre($copieEdition2, null, false, $this->maintenant->modify('+10 days'));

        $this->assertSame(StatutReservation::EN_ATTENTE, $resA->getStatut(), "A veut l'édition 1 : pas servie");
        $this->assertSame(StatutReservation::DISPONIBLE, $resB->getStatut(), 'B accepte toute édition : servie');
    }

    public function testAnnulerUneReservationPrete_ProposeLExemplaireAuSuivant(): void
    {
        $site = $this->creerSite();
        [$oeuvre, , $copie] = $this->creerOeuvreAvecCopie($site);
        $emprunteur = $this->creerAdherent(site: $site);
        $a = $this->creerAdherent(site: $site);
        $b = $this->creerAdherent(site: $site);

        $this->prets->emprunter($emprunteur, $copie, $this->maintenant);
        $resA = $this->reservations->reserver($a, $oeuvre, null, $site, $this->maintenant->modify('+1 hour'));
        $resB = $this->reservations->reserver($b, $oeuvre, null, $site, $this->maintenant->modify('+2 hours'));
        $this->prets->rendre($copie, null, false, $this->maintenant->modify('+10 days'));

        $this->reservations->annuler($resA, $this->maintenant->modify('+11 days'));

        $this->assertSame(StatutReservation::ANNULEE, $resA->getStatut());
        $this->assertSame(StatutReservation::DISPONIBLE, $resB->getStatut());
        $this->assertSame($copie, $resB->getExemplaireReserve());
    }

    public function testAnnulerSansSuivantRemetLExemplaireEnRayon(): void
    {
        $site = $this->creerSite();
        [$oeuvre, , $copie] = $this->creerOeuvreAvecCopie($site);
        $adherent = $this->creerAdherent(site: $site);
        $reservation = $this->reservations->reserver($adherent, $oeuvre, null, $site, $this->maintenant);
        $this->assertSame(StatutExemplaire::RESERVE, $copie->getStatut());

        $this->reservations->annuler($reservation, $this->maintenant->modify('+1 day'));

        $this->assertSame(StatutReservation::ANNULEE, $reservation->getStatut());
        $this->assertSame(StatutExemplaire::DISPONIBLE, $copie->getStatut());
        $this->assertNull($reservation->getExemplaireReserve());
    }

    public function testAnnulerUneReservationDejaRetireeEstRefuse(): void
    {
        $site = $this->creerSite();
        [$oeuvre, , $copie] = $this->creerOeuvreAvecCopie($site);
        $adherent = $this->creerAdherent(site: $site);
        $reservation = $this->reservations->reserver($adherent, $oeuvre, null, $site, $this->maintenant);
        $this->prets->emprunter($adherent, $copie, $this->maintenant->modify('+1 day'));

        $this->assertRefus(fn () => $this->reservations->annuler($reservation), 'ne peut plus');
    }

    public function testUneReservationNonRetireeExpireEtLExemplaireRetourneEnRayon(): void
    {
        $site = $this->creerSite();
        [$oeuvre, , $copie] = $this->creerOeuvreAvecCopie($site);
        $adherent = $this->creerAdherent(site: $site);
        $reservation = $this->reservations->reserver($adherent, $oeuvre, null, $site, $this->maintenant); // expire le 25/11

        $nombre = $this->reservations->expirerReservations(new \DateTimeImmutable('2026-11-26'));

        $this->assertSame(1, $nombre);
        $this->assertSame(StatutReservation::EXPIREE, $reservation->getStatut());
        $this->assertSame(StatutExemplaire::DISPONIBLE, $copie->getStatut());
    }

    public function testUneReservationExpireePasseLExemplaireAuSuivant(): void
    {
        $site = $this->creerSite();
        [$oeuvre, , $copie] = $this->creerOeuvreAvecCopie($site);
        $emprunteur = $this->creerAdherent(site: $site);
        $a = $this->creerAdherent(site: $site);
        $b = $this->creerAdherent(site: $site);

        $this->prets->emprunter($emprunteur, $copie, $this->maintenant);
        $resA = $this->reservations->reserver($a, $oeuvre, null, $site, $this->maintenant->modify('+1 hour'));
        $resB = $this->reservations->reserver($b, $oeuvre, null, $site, $this->maintenant->modify('+2 hours'));
        $this->prets->rendre($copie, null, false, new \DateTimeImmutable('2026-11-20 10:00')); // A : expire le 01/12

        $this->reservations->expirerReservations(new \DateTimeImmutable('2026-12-02'));

        $this->assertSame(StatutReservation::EXPIREE, $resA->getStatut());
        $this->assertSame(StatutReservation::DISPONIBLE, $resB->getStatut());
        $this->assertSame(StatutExemplaire::RESERVE, $copie->getStatut());
    }

    public function testLesReservationsNonEchuesNeSontPasTouchees(): void
    {
        $site = $this->creerSite();
        [$oeuvre, , $copie] = $this->creerOeuvreAvecCopie($site);
        $adherent = $this->creerAdherent(site: $site);
        $reservation = $this->reservations->reserver($adherent, $oeuvre, null, $site, $this->maintenant);

        $nombre = $this->reservations->expirerReservations(new \DateTimeImmutable('2026-11-20'));

        $this->assertSame(0, $nombre);
        $this->assertSame(StatutReservation::DISPONIBLE, $reservation->getStatut());
        $this->assertSame(StatutExemplaire::RESERVE, $copie->getStatut());
    }
}