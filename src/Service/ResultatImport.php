<?php

namespace App\Service;

use App\Entity\Edition;
use App\Enum\StatutImport;

final class ResultatImport
{
    public function __construct(
        public readonly string $isbn,
        public readonly StatutImport $statut,
        public readonly string $message,
        public readonly ?Edition $edition = null,
    ) {
    }
}
