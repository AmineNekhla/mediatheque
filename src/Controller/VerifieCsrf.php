<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;

/** Toutes les actions POST des écrans portent un jeton CSRF caché (champ "_token"). */
trait VerifieCsrf
{
    private function verifierCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('action', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
