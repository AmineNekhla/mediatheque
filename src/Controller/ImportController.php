<?php

namespace App\Controller;

use App\Service\ImportEditionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ImportController extends AbstractController
{
    use VerifieCsrf;

    private const MAX_PAR_LOT = 10; // ménage l'API externe et le temps de réponse

    #[Route('/biblio/import', name: 'app_biblio_import', methods: ['GET', 'POST'])]
    public function import(Request $request, ImportEditionService $import): Response
    {
        $resultats = [];
        $saisie = '';

        if ($request->isMethod('POST')) {
            $this->verifierCsrf($request);

            $saisie = (string) $request->request->get('isbns', '');
            $liste = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', $saisie) ?: []))));

            if (count($liste) > self::MAX_PAR_LOT) {
                $this->addFlash('erreur', sprintf('Maximum %d ISBN par import : les suivants ont été ignorés.', self::MAX_PAR_LOT));
                $liste = array_slice($liste, 0, self::MAX_PAR_LOT);
            }

            // Chaque ISBN est traité indépendamment : un échec n'arrête pas le lot.
            foreach ($liste as $isbn) {
                $resultats[] = $import->importer($isbn);
            }
        }

        return $this->render('biblio/import.html.twig', ['resultats' => $resultats, 'saisie' => $saisie]);
    }
}
