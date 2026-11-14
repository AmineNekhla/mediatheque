<?php

namespace App\Tests\Controller;

use App\Entity\Oeuvre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CatalogueTest extends WebTestCase
{
    private function creerOeuvre(string $titre): Oeuvre
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $oeuvre = (new Oeuvre())->setTitre($titre);
        $em->persist($oeuvre);
        $em->flush();

        return $oeuvre;
    }

    public function testLaRechercheTrouveUneOeuvreParSonTitre(): void
    {
        $client = static::createClient();
        $this->creerOeuvre('Le grand test de Fès');

        $client->request('GET', '/', ['q' => 'grand test']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Le grand test de Fès');
    }

    public function testUneRechercheSansResultatLeDit(): void
    {
        $client = static::createClient();

        $client->request('GET', '/', ['q' => 'zzz-introuvable-zzz']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Aucun résultat');
    }

    public function testLaFicheOeuvreEstPublique(): void
    {
        $client = static::createClient();
        $oeuvre = $this->creerOeuvre('Fiche publique');

        $client->request('GET', '/catalogue/' . $oeuvre->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Fiche publique');
    }

    public function testReserverEstInterditAuxAnonymes(): void
    {
        $client = static::createClient();
        $oeuvre = $this->creerOeuvre('Interdit');

        $client->request('POST', '/catalogue/' . $oeuvre->getId() . '/reserver');

        $this->assertResponseRedirects('/connexion');
    }

    public function testLeGuichetEstInterditAuxAnonymes(): void
    {
        $client = static::createClient();

        $client->request('GET', '/biblio');

        $this->assertResponseRedirects('/connexion');
    }
}
