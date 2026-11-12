<?php

namespace App\Enum;

enum StatutPenalite: string
{
    case IMPAYEE = 'IMPAYEE';
    case PAYEE = 'PAYEE';
    case ANNULEE = 'ANNULEE';
}