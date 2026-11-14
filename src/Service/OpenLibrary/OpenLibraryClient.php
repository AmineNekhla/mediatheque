<?php

namespace App\Service\OpenLibrary;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenLibraryClient
{
    // Open Library demande d'identifier l'application dans le User-Agent.
    private const USER_AGENT = 'MediathequeReseau/1.0 (projet PFE; https://github.com/AmineNekhla/mediatheque)';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $baseUrl = 'https://openlibrary.org',
    ) {
    }

    /**
     * @return DonneesLivre|null null si Open Library ne connaît pas cet ISBN (ou si la fiche est inexploitable)
     *
     * @throws ServiceExterneException réseau, délai dépassé, statut HTTP anormal, JSON illisible
     */
    public function rechercherParIsbn(string $isbn): ?DonneesLivre
    {
        try {
            $reponse = $this->http->request('GET', $this->baseUrl . '/api/books', [
                'query' => ['bibkeys' => 'ISBN:' . $isbn, 'format' => 'json', 'jscmd' => 'data'],
                'headers' => ['User-Agent' => self::USER_AGENT, 'Accept' => 'application/json'],
                'timeout' => 5,       // inactivité maximale
                'max_duration' => 10, // durée totale maximale
            ]);

            $statut = $reponse->getStatusCode();
            if ($statut !== 200) {
                throw new ServiceExterneException(sprintf('Open Library a répondu HTTP %d.', $statut));
            }

            $json = $reponse->toArray();
        } catch (ServiceExterneException $e) {
            throw $e;
        } catch (ExceptionInterface $e) {
            throw new ServiceExterneException('Open Library est injoignable, trop lent ou a renvoyé une réponse illisible.', 0, $e);
        }

        $livre = $json['ISBN:' . $isbn] ?? null;
        if (!is_array($livre)) {
            return null; // ISBN inconnu : Open Library renvoie un objet vide
        }

        $titre = trim((string) ($livre['title'] ?? ''));
        if ($titre === '') {
            return null; // fiche inexploitable sans titre
        }

        $auteurs = [];
        foreach ((array) ($livre['authors'] ?? []) as $auteur) {
            $nom = is_array($auteur) ? trim((string) ($auteur['name'] ?? '')) : '';
            if ($nom !== '') {
                $auteurs[] = $nom;
            }
        }

        $editeur = null;
        foreach ((array) ($livre['publishers'] ?? []) as $maison) {
            $nom = is_array($maison) ? trim((string) ($maison['name'] ?? '')) : '';
            if ($nom !== '') {
                $editeur = $nom;
                break;
            }
        }

        // La date arrive en texte libre ("1988", "October 1, 1988", "Jan 2001") : on extrait l'année.
        $annee = null;
        if (preg_match('/\b(1[5-9]\d{2}|20\d{2})\b/', (string) ($livre['publish_date'] ?? ''), $m) === 1) {
            $annee = (int) $m[1];
        }

        $pages = (int) ($livre['number_of_pages'] ?? 0);

        // On ne retient que des couvertures en https.
        $couverture = null;
        foreach (['medium', 'large', 'small'] as $taille) {
            $url = $livre['cover'][$taille] ?? null;
            if (is_string($url) && str_starts_with($url, 'https://')) {
                $couverture = $url;
                break;
            }
        }

        return new DonneesLivre($titre, $auteurs, $editeur, $annee, $pages > 0 ? $pages : null, $couverture);
    }
}
