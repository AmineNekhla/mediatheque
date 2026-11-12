<?php

namespace App\Enum;

enum StatutPret: string
{
    case EN_COURS = 'EN_COURS';
    case RENDU = 'RENDU';
    case EN_RETARD = 'EN_RETARD';
    case PERDU = 'PERDU';
}