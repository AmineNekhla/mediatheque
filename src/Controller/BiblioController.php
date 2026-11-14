<?php

namespace App\Controller;

use App\Entity\Adherent;
use App\Entity\Exemplaire;
use App\Entity\Penalite;
use App\Entity\Pret;
use App\Entity\Site;
use App\Entity\Transfert;
use App\Enum\StatutExemplaire;
use App\Enum\StatutPenalite;
use App\Enum\StatutPret;
use App\Exception\RegleMetierException;
use App\Service\PretService;
use App\Service\TransfertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/biblio')]
class BiblioController extends AbstractController
{
    use VerifieCsrf;

    #[Route('', name: 'app_biblio')]
    public function guichet(EntityManagerInterface $em): Response
    {
        $transferts = $em->createQueryBuilder()
            ->select('t')->from(Transfert::class, 't')
            ->where('t.dateReception IS NULL')
            ->orderBy('t.dateDemande', 'ASC')
            ->getQuery()->getResult();

        $retards = $em->createQueryBuilder()
            ->select('p')->from(Pret::class, 'p')
            ->where('p.statut = :s')
            ->orderBy('p.dateRetourPrevue', 'ASC')
            ->setMaxResults(20)
            ->setParameter('s', StatutPret::EN_RETARD->value)
            ->getQuery()->getResult();

        $penalites = $em->createQueryBuilder()
            ->select('pe')->from(Penalite::class, 'pe')
            ->where('pe.statut = :s')
            ->orderBy('pe.dateCreation', 'DESC')
            ->setMaxResults(20)
            ->setParameter('s', StatutPenalite::IMPAYEE->value)
            ->getQuery()->getResult();

        return $this->render('biblio/guichet.html.twig', [
            'sites' => $em->getRepository(Site::class)->findAll(),
            'transferts' => $transferts,
            'retards' => $retards,
            'penalites' => $penalites,
        ]);
    }

    #[Route('/emprunter', name: 'app_biblio_emprunter', methods: ['POST'])]
    public function emprunter(Request $request, EntityManagerInterface $em, PretService $prets): Response
    {
        $this->verifierCsrf($request);

        $adherent = $em->getRepository(Adherent::class)->findOneBy(['numeroCarte' => trim((string) $request->request->get('carte'))]);
        $copie = $em->getRepository(Exemplaire::class)->findOneBy(['codeBarres' => trim((string) $request->request->get('code'))]);

        if ($adherent === null) {
            $this->addFlash('erreur', 'Carte d\'adhérent inconnue.');
        } elseif ($copie === null) {
            $this->addFlash('erreur', 'Code-barres inconnu.');
        } else {
            try {
                $pret = $prets->emprunter($adherent, $copie);
                $this->addFlash('ok', sprintf(
                    'Prêt enregistré : « %s » pour %s, à rendre avant le %s.',
                    $copie->getEdition()->getOeuvre()->getTitre(),
                    $adherent,
                    $pret->getDateRetourPrevue()->format('d/m/Y')
                ));
            } catch (RegleMetierException $e) {
                $this->addFlash('erreur', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_biblio');
    }

    #[Route('/rendre', name: 'app_biblio_rendre', methods: ['POST'])]
    public function rendre(Request $request, EntityManagerInterface $em, PretService $prets): Response
    {
        $this->verifierCsrf($request);

        $copie = $em->getRepository(Exemplaire::class)->findOneBy(['codeBarres' => trim((string) $request->request->get('code'))]);
        $siteId = (int) $request->request->get('site');
        $site = $siteId > 0 ? $em->find(Site::class, $siteId) : null;

        if ($copie === null) {
            $this->addFlash('erreur', 'Code-barres inconnu.');
        } else {
            try {
                $pret = $prets->rendre($copie, $site, $request->request->getBoolean('abime'));
                $this->addFlash('ok', sprintf('Retour enregistré : « %s ».', $copie->getEdition()->getOeuvre()->getTitre()));

                foreach ($em->getRepository(Penalite::class)->findBy(['pret' => $pret]) as $penalite) {
                    $this->addFlash('erreur', sprintf(
                        'Pénalité %s : %s MAD.',
                        $penalite->getType()->value,
                        number_format($penalite->getMontantCentimes() / 100, 2, ',', ' ')
                    ));
                }
                if ($copie->getStatut() === StatutExemplaire::RESERVE) {
                    $this->addFlash('ok', 'À mettre de côté : un adhérent attend ce document.');
                } elseif ($copie->getStatut() === StatutExemplaire::EN_TRANSIT) {
                    $this->addFlash('ok', 'À envoyer vers un autre site : un adhérent attend ce document (voir les transferts).');
                }
            } catch (RegleMetierException $e) {
                $this->addFlash('erreur', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_biblio');
    }

    #[Route('/transferts/{id}/recevoir', name: 'app_biblio_recevoir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function recevoir(Transfert $transfert, Request $request, TransfertService $transferts): Response
    {
        $this->verifierCsrf($request);

        try {
            $transferts->recevoir($transfert);
            $this->addFlash('ok', 'Transfert réceptionné.');
        } catch (RegleMetierException $e) {
            $this->addFlash('erreur', $e->getMessage());
        }

        return $this->redirectToRoute('app_biblio');
    }

    #[Route('/penalites/{id}/payer', name: 'app_biblio_payer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function payer(Penalite $penalite, Request $request, EntityManagerInterface $em): Response
    {
        $this->verifierCsrf($request);

        if ($penalite->getStatut() === StatutPenalite::IMPAYEE) {
            $penalite->setStatut(StatutPenalite::PAYEE);
            $em->flush();
            $this->addFlash('ok', 'Pénalité marquée comme payée.');
        }

        return $this->redirectToRoute('app_biblio');
    }
}
