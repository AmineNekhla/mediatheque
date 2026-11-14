<?php

namespace App\Enum;

enum StatutImport: string
{
    case CREE = 'CREE';
    case DEJA_PRESENT = 'DEJA_PRESENT';
    case INTROUVABLE = 'INTROUVABLE';
    case INVALIDE = 'INVALIDE';
    case ERREUR = 'ERREUR';
}
