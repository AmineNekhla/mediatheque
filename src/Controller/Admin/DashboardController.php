<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly AdminUrlGenerator $adminUrlGenerator)
    {
    }

    public function index(): Response
    {
        // Pas de page d'accueil : on arrive directement sur le catalogue.
        $url = $this->adminUrlGenerator
            ->setController(OeuvreCrudController::class)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Médiathèque');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord');

        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(OeuvreCrudController::class, 'Œuvres');
        yield MenuItem::linkTo(EditionCrudController::class, 'Éditions');
        yield MenuItem::linkTo(ExemplaireCrudController::class, 'Exemplaires');
        yield MenuItem::linkTo(PersonneCrudController::class, 'Personnes');
        yield MenuItem::linkTo(ContributionCrudController::class, 'Contributions');

        yield MenuItem::section('Circulation');
        yield MenuItem::linkTo(PretCrudController::class, 'Prêts');
        yield MenuItem::linkTo(ReservationCrudController::class, 'Réservations');
        yield MenuItem::linkTo(TransfertCrudController::class, 'Transferts');
        yield MenuItem::linkTo(PenaliteCrudController::class, 'Pénalités');

        yield MenuItem::section('Réseau');
        yield MenuItem::linkTo(AdherentCrudController::class, 'Adhérents');
        yield MenuItem::linkTo(FormuleAbonnementCrudController::class, 'Formules');
        yield MenuItem::linkTo(SiteCrudController::class, 'Sites');
    }
}