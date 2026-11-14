<?php

namespace App\Tests\Service;

use App\Service\OpenLibrary\OpenLibraryClient;
use App\Service\OpenLibrary\ServiceExterneException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenLibraryClientTest extends TestCase
{
    private const ISBN = '9780140328721';

    private function client(MockResponse $reponse): OpenLibraryClient
    {
        return new OpenLibraryClient(new MockHttpClient($reponse));
    }

    private function json(array $livre): MockResponse
    {
        return new MockResponse(json_encode(['ISBN:' . self::ISBN => $livre], JSON_THROW_ON_ERROR));
    }

    public function testExtraitLesDonneesDUneFicheComplete(): void
    {
        $donnees = $this->client($this->json([
            'title' => 'Fantastic Mr Fox',
            'authors' => [['name' => 'Roald Dahl']],
            'publishers' => [['name' => 'Puffin']],
            'publish_date' => 'October 1, 1988',
            'number_of_pages' => 96,
            'cover' => ['medium' => 'https://covers.openlibrary.org/b/id/1-M.jpg'],
        ]))->rechercherParIsbn(self::ISBN);

        $this->assertNotNull($donnees);
        $this->assertSame('Fantastic Mr Fox', $donnees->titre);
        $this->assertSame(['Roald Dahl'], $donnees->auteurs);
        $this->assertSame('Puffin', $donnees->editeur);
        $this->assertSame(1988, $donnees->annee);
        $this->assertSame(96, $donnees->pages);
        $this->assertSame('https://covers.openlibrary.org/b/id/1-M.jpg', $donnees->couverture);
    }

    public function testUnIsbnInconnuRetourneNull(): void
    {
        $this->assertNull($this->client(new MockResponse('{}'))->rechercherParIsbn(self::ISBN));
    }

    public function testUneFicheSansTitreEstInexploitable(): void
    {
        $this->assertNull($this->client($this->json(['authors' => [['name' => 'Quelqu\'un']]]))->rechercherParIsbn(self::ISBN));
    }

    public function testLesChampsAbsentsDonnentDesValeursNulles(): void
    {
        $donnees = $this->client($this->json(['title' => 'Titre seul']))->rechercherParIsbn(self::ISBN);

        $this->assertNotNull($donnees);
        $this->assertSame([], $donnees->auteurs);
        $this->assertNull($donnees->editeur);
        $this->assertNull($donnees->annee);
        $this->assertNull($donnees->pages);
        $this->assertNull($donnees->couverture);
    }

    public function testUneCouvertureEnHttpEstIgnoree(): void
    {
        $donnees = $this->client($this->json([
            'title' => 'Titre',
            'cover' => ['medium' => 'http://exemple.test/couv.jpg'],
        ]))->rechercherParIsbn(self::ISBN);

        $this->assertNull($donnees->couverture);
    }

    public function testUneErreurHttpLeveUneException(): void
    {
        $this->expectException(ServiceExterneException::class);
        $this->expectExceptionMessage('HTTP 500');

        $this->client(new MockResponse('', ['http_code' => 500]))->rechercherParIsbn(self::ISBN);
    }

    public function testUneErreurReseauLeveUneException(): void
    {
        $this->expectException(ServiceExterneException::class);

        $this->client(new MockResponse('', ['error' => 'timeout']))->rechercherParIsbn(self::ISBN);
    }

    public function testUnJsonIllisibleLeveUneException(): void
    {
        $this->expectException(ServiceExterneException::class);

        $this->client(new MockResponse('ceci n\'est pas du json'))->rechercherParIsbn(self::ISBN);
    }
}
