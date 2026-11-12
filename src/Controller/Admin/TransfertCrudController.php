<?php

namespace App\Controller\Admin;

use App\Entity\Transfert;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class TransfertCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Transfert::class;
    }
}
