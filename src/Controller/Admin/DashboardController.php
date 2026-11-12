<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Adherent;
use App\Entity\Contribution;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\FormuleAbonnement;
use App\Entity\Oeuvre;
use App\Entity\Penalite;
use App\Entity\Personne;
use App\Entity\Pret;
use App\Entity\Reservation;
use App\Entity\Site;
use App\Entity\Transfert;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return parent::index();

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // return $this->redirectToRoute('admin_user_index');

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirectToRoute('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Mediatheque');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(OeuvreCrudController::class, 'Œuvres', 'fa fa-book');
        yield MenuItem::linkTo(EditionCrudController::class, 'Éditions', 'fa fa-bookmark');
        yield MenuItem::linkTo(ExemplaireCrudController::class, 'Exemplaires', 'fa fa-barcode');
        yield MenuItem::linkTo(PersonneCrudController::class, 'Personnes', 'fa fa-user-pen');
        yield MenuItem::linkTo(ContributionCrudController::class, 'Contributions', 'fa fa-link');

        yield MenuItem::section('Circulation');
        yield MenuItem::linkTo(PretCrudController::class, 'Prêts', 'fa fa-right-left');
        yield MenuItem::linkTo(ReservationCrudController::class, 'Réservations', 'fa fa-clock');
        yield MenuItem::linkTo(TransfertCrudController::class, 'Transferts', 'fa fa-truck');
        yield MenuItem::linkTo(PenaliteCrudController::class, 'Pénalités', 'fa fa-coins');

        yield MenuItem::section('Réseau');
        yield MenuItem::linkTo(AdherentCrudController::class, 'Adhérents', 'fa fa-id-card');
        yield MenuItem::linkTo(FormuleAbonnementCrudController::class, 'Formules', 'fa fa-tags');
        yield MenuItem::linkTo(SiteCrudController::class, 'Sites', 'fa fa-building');
    }
}
