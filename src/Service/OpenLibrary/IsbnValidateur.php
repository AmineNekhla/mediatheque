<?php

namespace App\Service\OpenLibrary;

final class IsbnValidateur
{
    /**
     * Retourne l'ISBN sans tirets ni espaces (majuscules), ou null s'il est invalide
     * (format ou clé de contrôle). Accepte ISBN-10 et ISBN-13.
     */
    public static function normaliser(string $saisie): ?string
    {
        $isbn = strtoupper((string) preg_replace('/[\s\-]/', '', $saisie));

        if (preg_match('/^\d{13}$/', $isbn) === 1) {
            return self::cleIsbn13Valide($isbn) ? $isbn : null;
        }
        if (preg_match('/^\d{9}[\dX]$/', $isbn) === 1) {
            return self::cleIsbn10Valide($isbn) ? $isbn : null;
        }

        return null;
    }

    private static function cleIsbn13Valide(string $isbn): bool
    {
        $somme = 0;
        for ($i = 0; $i < 13; ++$i) {
            $somme += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $somme % 10 === 0;
    }

    private static function cleIsbn10Valide(string $isbn): bool
    {
        $somme = 0;
        for ($i = 0; $i < 10; ++$i) {
            $valeur = $isbn[$i] === 'X' ? 10 : (int) $isbn[$i];
            $somme += $valeur * (10 - $i);
        }

        return $somme % 11 === 0;
    }
}
