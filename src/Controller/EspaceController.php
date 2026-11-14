<?php

namespace App\Controller;

use App\Entity\Penalite;
use App\Entity\Pret;
use App\Entity\Reservation;
use App\Enum\StatutPenalite;
use App\Enum\StatutPret;
use App\Enum\StatutReservation;
use App\Exception\RegleMetierException;
use App\Service\AdherentCourant;
use App\Service\PretService;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mon-espace')]
class EspaceController extends AbstractController
{
    use VerifieCsrf;

    #[Route('', name: 'app_espace')]
    public function index(EntityManagerInterface $em, AdherentCourant $courant): Response
    {
        $adherent = $courant->obtenir();
        if ($adherent === null) {
            return $this->render('espace/index.html.twig', ['adherent' => null]);
        }

        $prets = $em->createQueryBuilder()
            ->select('p')->from(Pret::class, 'p')
            ->where('p.adherent = :a')->andWhere('p.statut IN (:s)')
            ->orderBy('p.dateRetourPrevue', 'ASC')
            ->setParameter('a', $adherent)
            ->setParameter('s', [StatutPret::EN_COURS->value, StatutPret::EN_RETARD->value])
            ->getQuery()->getResult();

        $reservations = $em->createQueryBuilder()
            ->select('r')->from(Reservation::class, 'r')
            ->where('r.adherent = :a')->andWhere('r.statut IN (:s)')
            ->orderBy('r.dateCreation', 'ASC')
            ->setParameter('a', $adherent)
            ->setParameter('s', [StatutReservation::EN_ATTENTE->value, StatutReservation::DISPONIBLE->value])
            ->getQuery()->getResult();

        $penalites = $em->createQueryBuilder()
            ->select('pe')->from(Penalite::class, 'pe')
            ->where('pe.adherent = :a')->andWhere('pe.statut = :s')
            ->orderBy('pe.dateCreation', 'DESC')
            ->setParameter('a', $adherent)
            ->setParameter('s', StatutPenalite::IMPAYEE->value)
            ->getQuery()->getResult();

        return $this->render('espace/index.html.twig', [
            'adherent' => $adherent,
            'prets' => $prets,
            'reservations' => $reservations,
            'penalites' => $penalites,
        ]);
    }

    #[Route('/prets/{id}/prolonger', name: 'app_espace_prolonger', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function prolonger(Pret $pret, Request $request, PretService $prets, AdherentCourant $courant): Response
    {
        $this->verifierCsrf($request);

        // Un adhérent ne peut agir que sur SES prêts.
        if ($courant->obtenir() === null || $pret->getAdherent() !== $courant->obtenir()) {
            throw $this->createAccessDeniedException();
        }

        try {
            $prets->prolonger($pret);
            $this->addFlash('ok', 'Prêt prolongé : à rendre avant le ' . $pret->getDateRetourPrevue()->format('d/m/Y') . '.');
        } catch (RegleMetierException $e) {
            $this->addFlash('erreur', $e->getMessage());
        }

        return $this->redirectToRoute('app_espace');
    }

    #[Route('/reservations/{id}/annuler', name: 'app_espace_annuler', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function annuler(Reservation $reservation, Request $request, ReservationService $reservations, AdherentCourant $courant): Response
    {
        $this->verifierCsrf($request);

        if ($courant->obtenir() === null || $reservation->getAdherent() !== $courant->obtenir()) {
            throw $this->createAccessDeniedException();
        }

        try {
            $reservations->annuler($reservation);
            $this->addFlash('ok', 'Réservation annulée.');
        } catch (RegleMetierException $e) {
            $this->addFlash('erreur', $e->getMessage());
        }

        return $this->redirectToRoute('app_espace');
    }
}
