<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityAccessTest extends WebTestCase
{
    private function utilisateur(string $email, array $roles): Utilisateur
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $utilisateur = (new Utilisateur())->setEmail($email)->setRoles($roles)->setMotDePasse('non-utilise');
        $em->persist($utilisateur);
        $em->flush();

        return $utilisateur;
    }

    private function client(): KernelBrowser
    {
        return static::createClient();
    }

    public function testLaPageDeConnexionEstPublique(): void
    {
        $client = $this->client();
        $client->request('GET', '/connexion');

        $this->assertResponseIsSuccessful();
    }

    public function testLeBackOfficeEstInterditAuxAnonymes(): void
    {
        $client = $this->client();
        $client->request('GET', '/admin');

        $this->assertResponseRedirects('/connexion');
    }

    public function testUnAdherentNeVoitPasLeBackOffice(): void
    {
        $client = $this->client();
        $client->loginUser($this->utilisateur('adherent@test.ma', ['ROLE_ADHERENT']));
        $client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUnBibliothecaireAccedeAuBackOffice(): void
    {
        $client = $this->client();
        $client->loginUser($this->utilisateur('biblio@test.ma', ['ROLE_BIBLIOTHECAIRE']));
        $client->request('GET', '/admin');

        $this->assertNotSame(403, $client->getResponse()->getStatusCode());
        $this->assertStringNotContainsString('/connexion', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testUnAdministrateurHeriteDesDroitsDuBibliothecaire(): void
    {
        $client = $this->client();
        $client->loginUser($this->utilisateur('admin@test.ma', ['ROLE_ADMIN']));
        $client->request('GET', '/admin');

        $this->assertNotSame(403, $client->getResponse()->getStatusCode());
    }

    public function testUnBibliothecaireNEstPasUnAdherent(): void
    {
        $client = $this->client();
        $client->loginUser($this->utilisateur('biblio2@test.ma', ['ROLE_BIBLIOTHECAIRE']));
        $client->request('GET', '/mon-espace');

        $this->assertResponseStatusCodeSame(403);
    }
}