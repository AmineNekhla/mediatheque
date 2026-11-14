<?php

namespace App\Service;

use App\Entity\Adherent;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/** Fiche adhérent de l'utilisateur connecté (null pour le personnel). */
class AdherentCourant
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function obtenir(): ?Adherent
    {
        $utilisateur = $this->security->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return null;
        }

        return $this->em->getRepository(Adherent::class)->findOneBy(['utilisateur' => $utilisateur]);
    }
}
