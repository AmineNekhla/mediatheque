<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login')]
    public function login(AuthenticationUtils $utils): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_accueil');
        }

        return $this->render('security/login.html.twig', [
            'dernier_email' => $utils->getLastUsername(),
            'erreur' => $utils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout')]
    public function logout(): never
    {
        // Intercepté par le pare-feu : ce code n'est jamais exécuté.
        throw new \LogicException('Cette route est gérée par le pare-feu.');
    }
}