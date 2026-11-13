<?php

namespace App\Tests\Service;

use App\Entity\Transfert;
use App\Enum\MotifTransfert;
use App\Enum\StatutExemplaire;
use App\Enum\StatutReservation;
use App\Tests\BaseTestCase;

class TransfertServiceTest extends BaseTestCase
{
    public function testDemanderMetLExemplaireEnTransit(): void
    {
        $origine = $this->creerSite();
        $destination = $this->creerSite();
        [, , $copie] = $this->creerOeuvreAvecCopie($origine);

        $transfert = $this->transferts->demander($copie, $destination, MotifTransfert::REEQUILIBRAGE, $this->maintenant);

        $this->assertSame(StatutExemplaire::EN_TRANSIT, $copie->getStatut());
        $this->assertSame($origine, $transfert->getSiteOrigine());
        $this->assertSame($destination, $transfert->getSiteDestination());
        $this->assertNull($transfert->getDateReception());
    }

    public function testDemanderRefuseSiLExemplaireNEstPasDisponible(): void
    {
        $site = $this->creerSite();
        [, , $copie] = $this->creerOeuvreAvecCopie($site, StatutExemplaire::EMPRUNTE);

        $this->assertRefus(fn () => $this->transferts->demander($copie, $this->creerSite()), 'disponible');
    }

    public function testDemanderRefuseSiDejaSurLeSiteDeDestination(): void
    {
        $site = $this->creerSite();
        [, , $copie] = $this->creerOeuvreAvecCopie($site);

        $this->assertRefus(fn () => $this->transferts->demander($copie, $site), 'déjà sur ce site');
    }

    public function testRecevoirUnReequilibrageRemetLExemplaireEnRayonSurLeNouveauSite(): void
    {
        $origine = $this->creerSite();
        $destination = $this->creerSite();
        [, , $copie] = $this->creerOeuvreAvecCopie($origine);
        $transfert = $this->transferts->demander($copie, $destination, MotifTransfert::REEQUILIBRAGE, $this->maintenant);

        $this->transferts->recevoir($transfert, $this->maintenant->modify('+1 day'));

        $this->assertSame(StatutExemplaire::DISPONIBLE, $copie->getStatut());
        $this->assertSame($destination, $copie->getSiteActuel());
        $this->assertNotNull($transfert->getDateReception());
    }

    public function testRecevoirUnTransfertDeReservationMetDeCoteLExemplaire(): void
    {
        $siteRetrait = $this->creerSite();
        $autreSite = $this->creerSite();
        [$oeuvre, , $copie] = $this->creerOeuvreAvecCopie($autreSite);
        $adherent = $this->creerAdherent(site: $siteRetrait);
        $reservation = $this->reservations->reserver($adherent, $oeuvre, null, $siteRetrait, $this->maintenant);
        $transfert = $this->em->getRepository(Transfert::class)->findOneBy(['exemplaire' => $copie]);

        $this->transferts->recevoir($transfert, $this->maintenant->modify('+1 day'));

        $this->assertSame(StatutReservation::DISPONIBLE, $reservation->getStatut());
        $this->assertSame(StatutExemplaire::RESERVE, $copie->getStatut());
        $this->assertSame($siteRetrait, $copie->getSiteActuel());
        $this->assertNotNull($reservation->getDateExpirationRetrait());
    }

    public function testRecevoirDeuxFoisLeMemeTransfertEstRefuse(): void
    {
        $destination = $this->creerSite();
        [, , $copie] = $this->creerOeuvreAvecCopie($this->creerSite());
        $transfert = $this->transferts->demander($copie, $destination, MotifTransfert::REEQUILIBRAGE, $this->maintenant);
        $this->transferts->recevoir($transfert, $this->maintenant->modify('+1 day'));

        $this->assertRefus(fn () => $this->transferts->recevoir($transfert), 'déjà été reçu');
    }

    public function testReservationAnnuleePendantLeTrajetLibereLExemplaireALArrivee(): void
    {
        $siteRetrait = $this->creerSite();
        $autreSite = $this->creerSite();
        [$oeuvre, , $copie] = $this->creerOeuvreAvecCopie($autreSite);
        $adherent = $this->creerAdherent(site: $siteRetrait);
        $reservation = $this->reservations->reserver($adherent, $oeuvre, null, $siteRetrait, $this->maintenant);
        $transfert = $this->em->getRepository(Transfert::class)->findOneBy(['exemplaire' => $copie]);

        $this->reservations->annuler($reservation, $this->maintenant->modify('+1 hour'));
        $this->transferts->recevoir($transfert, $this->maintenant->modify('+1 day'));

        $this->assertSame(StatutReservation::ANNULEE, $reservation->getStatut());
        $this->assertSame(StatutExemplaire::DISPONIBLE, $copie->getStatut());
        $this->assertSame($siteRetrait, $copie->getSiteActuel());
    }
}