<?php

namespace App\Tests\Service;

use App\Entity\Contribution;
use App\Entity\Edition;
use App\Entity\Oeuvre;
use App\Entity\Personne;
use App\Enum\RoleContribution;
use App\Enum\SourceEdition;
use App\Enum\StatutImport;
use App\Service\ImportEditionService;
use App\Service\OpenLibrary\OpenLibraryClient;
use App\Tests\BaseTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ImportEditionServiceTest extends BaseTestCase
{
    private const ISBN = '9780140328721';

    /** @param MockResponse|callable $reponse */
    private function service(MockResponse|callable $reponse): ImportEditionService
    {
        return new ImportEditionService($this->em, new OpenLibraryClient(new MockHttpClient($reponse)));
    }

    private function reponseLivre(array $surcharge = []): MockResponse
    {
        $livre = array_merge([
            'title' => 'Fantastic Mr Fox',
            'authors' => [['name' => 'Roald Dahl']],
            'publishers' => [['name' => 'Puffin']],
            'publish_date' => '1988',
            'number_of_pages' => 96,
        ], $surcharge);

        return new MockResponse(json_encode(['ISBN:' . self::ISBN => $livre], JSON_THROW_ON_ERROR));
    }

    /** Client qui échoue si on l'appelle : prouve qu'aucun appel réseau n'a lieu. */
    private function clientInterdit(): callable
    {
        return fn () => throw new \LogicException('Open Library ne devait pas être appelé.');
    }

    public function testImporteUneFicheCompleteSansLaMarquerAVerifier(): void
    {
        $resultat = $this->service($this->reponseLivre())->importer('978-0-14-032872-1');

        $this->assertSame(StatutImport::CREE, $resultat->statut);
        $edition = $resultat->edition;
        $this->assertSame(self::ISBN, $edition->getIsbn());
        $this->assertSame(SourceEdition::OPEN_LIBRARY, $edition->getSource());
        $this->assertFalse($edition->isAVerifier());
        $this->assertSame('Fantastic Mr Fox', $edition->getOeuvre()->getTitre());
        $this->assertSame('Puffin', $edition->getEditeur());
        $this->assertSame(1988, $edition->getAnneePublication());

        $contributions = $this->em->getRepository(Contribution::class)->findBy(['oeuvre' => $edition->getOeuvre()]);
        $this->assertCount(1, $contributions);
        $this->assertSame(RoleContribution::AUTEUR, $contributions[0]->getRole());
        $this->assertSame('Dahl', $contributions[0]->getPersonne()->getNom());
        $this->assertSame('Roald', $contributions[0]->getPersonne()->getPrenom());
    }

    public function testUneFicheIncompleteEstMarqueeAVerifier(): void
    {
        $resultat = $this->service($this->reponseLivre(['publishers' => []]))->importer(self::ISBN);

        $this->assertSame(StatutImport::CREE, $resultat->statut);
        $this->assertTrue($resultat->edition->isAVerifier());
        $this->assertStringContainsString('à vérifier', $resultat->message);
    }

    public function testUnIsbnDejaPresentNEstJamaisModifie(): void
    {
        $oeuvre = $this->creerOeuvre('Titre local');
        $edition = $this->creerEdition($oeuvre);
        $edition->setIsbn(self::ISBN)->setEditeur('Éditeur local');
        $this->em->flush();

        $resultat = $this->service($this->clientInterdit())->importer(self::ISBN);

        $this->assertSame(StatutImport::DEJA_PRESENT, $resultat->statut);
        $this->assertSame('Titre local', $resultat->edition->getOeuvre()->getTitre());
        $this->assertSame('Éditeur local', $resultat->edition->getEditeur());
        $this->assertSame(1, $this->em->getRepository(Oeuvre::class)->count([]));
        $this->assertSame(1, $this->em->getRepository(Edition::class)->count([]));
    }

    public function testUnIsbnInconnuNeCreeRien(): void
    {
        $resultat = $this->service(new MockResponse('{}'))->importer(self::ISBN);

        $this->assertSame(StatutImport::INTROUVABLE, $resultat->statut);
        $this->assertSame(0, $this->em->getRepository(Edition::class)->count([]));
    }

    public function testUnePanneReseauNeCreeRienEtEstSignalee(): void
    {
        $resultat = $this->service(new MockResponse('', ['error' => 'timeout']))->importer(self::ISBN);

        $this->assertSame(StatutImport::ERREUR, $resultat->statut);
        $this->assertSame(0, $this->em->getRepository(Edition::class)->count([]));
        $this->assertSame(0, $this->em->getRepository(Oeuvre::class)->count([]));
    }

    public function testUnIsbnInvalideNAppellePasOpenLibrary(): void
    {
        $resultat = $this->service($this->clientInterdit())->importer('9780140328722');

        $this->assertSame(StatutImport::INVALIDE, $resultat->statut);
    }

    public function testUneAuteureExistanteEstReutilisee(): void
    {
        $existante = (new Personne())->setNom('Dahl')->setPrenom('Roald');
        $this->em->persist($existante);
        $this->em->flush();

        $resultat = $this->service($this->reponseLivre())->importer(self::ISBN);

        $this->assertSame(StatutImport::CREE, $resultat->statut);
        $this->assertSame(1, $this->em->getRepository(Personne::class)->count(['nom' => 'Dahl']));
    }
}
