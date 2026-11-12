<?php

namespace App\Controller\Admin;

use App\Entity\Pret;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class PretCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Pret::class;
    }
}
