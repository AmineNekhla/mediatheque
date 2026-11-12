<?php

namespace App\Enum;

enum StatutExemplaire: string
{
    case DISPONIBLE = 'DISPONIBLE';
    case EMPRUNTE = 'EMPRUNTE';
    case RESERVE = 'RESERVE';
    case EN_TRANSIT = 'EN_TRANSIT';
    case PERDU = 'PERDU';
    case ABIME = 'ABIME';
    case RETIRE = 'RETIRE';
}