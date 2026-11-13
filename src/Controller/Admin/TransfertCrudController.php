<?php

namespace App\Controller\Admin;

use App\Entity\Transfert;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class TransfertCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Transfert::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('exemplaire');
        yield AssociationField::new('siteOrigine');
        yield AssociationField::new('siteDestination');
        yield AssociationField::new('reservation');
        yield ChoiceField::new('motif');
        yield DateTimeField::new('dateDemande');
        yield DateTimeField::new('dateReception');
    }
}