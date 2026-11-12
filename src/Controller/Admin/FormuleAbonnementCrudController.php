<?php

namespace App\Controller\Admin;

use App\Entity\FormuleAbonnement;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class FormuleAbonnementCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FormuleAbonnement::class;
    }
}
