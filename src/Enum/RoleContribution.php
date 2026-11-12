<?php

namespace App\Enum;

enum RoleContribution: string
{
    case AUTEUR = 'AUTEUR';
    case ILLUSTRATEUR = 'ILLUSTRATEUR';
    case SCENARISTE = 'SCENARISTE';
    case DESSINATEUR = 'DESSINATEUR';
    case COLORISTE = 'COLORISTE';
    case TRADUCTEUR = 'TRADUCTEUR';
    case PREFACIER = 'PREFACIER';
    case REALISATEUR = 'REALISATEUR';
    case ACTEUR = 'ACTEUR';
}