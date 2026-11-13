<?php

namespace App\Service;

class Calendrier
{
    /** Ajoute des jours ouvrés (lundi à vendredi). Les jours fériés ne sont pas gérés. */
    public function ajouterJoursOuvres(\DateTimeImmutable $debut, int $jours): \DateTimeImmutable
    {
        $date = $debut;
        $restants = $jours;

        while ($restants > 0) {
            $date = $date->modify('+1 day');
            if ((int) $date->format('N') < 6) { // 1 = lundi ... 5 = vendredi
                --$restants;
            }
        }

        return $date;
    }
}