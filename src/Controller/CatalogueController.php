<?php

namespace App\Controller;

use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Oeuvre;
use App\Entity\Site;
use App\Enum\StatutExemplaire;
use App\Enum\StatutReservation;
use App\Exception\RegleMetierException;
use App\Service\AdherentCourant;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CatalogueController extends AbstractController
{
    use VerifieCsrf;

    #[Route('/', name: 'app_accueil')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $q = trim((string) $request->query->get('q', ''));

        $qb = $em->createQueryBuilder()
            ->select('DISTINCT o')
            ->from(Oeuvre::class, 'o')
            ->leftJoin('o.editions', 'e')
            ->leftJoin('o.contributions', 'c')
            ->leftJoin('c.personne', 'p')
            ->orderBy('o.titre', 'ASC')
            ->setMaxResults(30);

        if ($q !== '') {
            $qb->where('o.titre LIKE :q OR e.isbn LIKE :q OR p.nom LIKE :q OR p.prenom LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        /** @var list<Oeuvre> $oeuvres */
        $oeuvres = $qb->getQuery()->getResult();

        // Nombre d'exemplaires disponibles par œuvre, en une seule requête.
        $disponibles = [];
        if ($oeuvres !== []) {
            $lignes = $em->createQueryBuilder()
                ->select('IDENTITY(ed.oeuvre) AS oeuvreId', 'COUNT(ex.id) AS nb')
                ->from(Exemplaire::class, 'ex')
                ->join('ex.edition', 'ed')
                ->where('ex.statut = :statut')
                ->andWhere('ed.oeuvre IN (:oeuvres)')
                ->groupBy('ed.oeuvre')
                ->setParameter('statut', StatutExemplaire::DISPONIBLE->value)
                ->setParameter('oeuvres', $oeuvres)
                ->getQuery()
                ->getArrayResult();

            foreach ($lignes as $ligne) {
                $disponibles[(int) $ligne['oeuvreId']] = (int) $ligne['nb'];
            }
        }

        return $this->render('catalogue/index.html.twig', [
            'q' => $q,
            'oeuvres' => $oeuvres,
            'disponibles' => $disponibles,
        ]);
    }

    #[Route('/catalogue/{id}', name: 'app_oeuvre', requirements: ['id' => '\d+'])]
    public function oeuvre(Oeuvre $oeuvre, EntityManagerInterface $em): Response
    {
        return $this->render('catalogue/oeuvre.html.twig', [
            'oeuvre' => $oeuvre,
            'sites' => $em->getRepository(Site::class)->findAll(),
        ]);
    }

    #[Route('/catalogue/{id}/reserver', name: 'app_reserver', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADHERENT')]
    public function reserver(
        Oeuvre $oeuvre,
        Request $request,
        EntityManagerInterface $em,
        ReservationService $reservations,
        AdherentCourant $courant,
    ): Response {
        $this->verifierCsrf($request);

        $adherent = $courant->obtenir();
        $site = $em->find(Site::class, (int) $request->request->get('site'));
        $editionId = (int) $request->request->get('edition');
        $edition = $editionId > 0 ? $em->find(Edition::class, $editionId) : null;

        if ($adherent === null || $site === null) {
            $this->addFlash('erreur', 'Réservation impossible : fiche adhérent ou site introuvable.');

            return $this->redirectToRoute('app_oeuvre', ['id' => $oeuvre->getId()]);
        }

        try {
            $reservation = $reservations->reserver($adherent, $oeuvre, $edition, $site);

            if ($reservation->getStatut() === StatutReservation::DISPONIBLE) {
                $this->addFlash('ok', sprintf(
                    'Un exemplaire vous attend à « %s » jusqu\'au %s.',
                    $site->getNom(),
                    $reservation->getDateExpirationRetrait()->format('d/m/Y')
                ));
            } elseif ($reservation->getExemplaireReserve() !== null) {
                $this->addFlash('ok', 'Un exemplaire est en route vers « ' . $site->getNom() . ' » : vous serez servi à son arrivée.');
            } else {
                $this->addFlash('ok', 'Réservation enregistrée : vous êtes dans la file d\'attente.');
            }
        } catch (RegleMetierException $e) {
            $this->addFlash('erreur', $e->getMessage());
        }

        return $this->redirectToRoute('app_oeuvre', ['id' => $oeuvre->getId()]);
    }
}
