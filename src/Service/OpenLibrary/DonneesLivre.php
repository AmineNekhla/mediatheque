<?php

namespace App\Service\OpenLibrary;

/** Données utiles extraites d'une réponse Open Library (déjà nettoyées). */
final class DonneesLivre
{
    /** @param list<string> $auteurs */
    public function __construct(
        public readonly string $titre,
        public readonly array $auteurs,
        public readonly ?string $editeur,
        public readonly ?int $annee,
        public readonly ?int $pages,
        public readonly ?string $couverture,
    ) {
    }
}
