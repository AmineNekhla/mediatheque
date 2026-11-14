<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ImportAccessTest extends WebTestCase
{
    private function utilisateur(string $email, array $roles): Utilisateur
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $utilisateur = (new Utilisateur())->setEmail($email)->setRoles($roles)->setMotDePasse('non-utilise');
        $em->persist($utilisateur);
        $em->flush();

        return $utilisateur;
    }

    public function testLImportEstInterditAuxAnonymes(): void
    {
        $client = static::createClient();
        $client->request('GET', '/biblio/import');

        $this->assertResponseRedirects('/connexion');
    }

    public function testLImportEstInterditAuxAdherents(): void
    {
        $client = static::createClient();
        $client->loginUser($this->utilisateur('adherent-import@test.ma', ['ROLE_ADHERENT']));
        $client->request('GET', '/biblio/import');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUnBibliothecaireVoitLeFormulaireDImport(): void
    {
        $client = static::createClient();
        $client->loginUser($this->utilisateur('biblio-import@test.ma', ['ROLE_BIBLIOTHECAIRE']));
        $client->request('GET', '/biblio/import');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('textarea[name=isbns]');
    }
}
