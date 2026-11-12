<?php

namespace App\Enum;

enum StatutReservation: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case DISPONIBLE = 'DISPONIBLE';
    case RETIREE = 'RETIREE';
    case ANNULEE = 'ANNULEE';
    case EXPIREE = 'EXPIREE';
}