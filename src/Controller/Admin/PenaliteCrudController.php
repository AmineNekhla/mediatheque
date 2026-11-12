<?php

namespace App\Controller\Admin;

use App\Entity\Penalite;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class PenaliteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Penalite::class;
    }
}
