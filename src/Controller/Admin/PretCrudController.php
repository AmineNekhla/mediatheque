<?php

namespace App\Controller\Admin;

use App\Entity\Pret;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class PretCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Pret::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('exemplaire');
        yield AssociationField::new('adherent');
        yield DateTimeField::new('dateEmprunt');
        yield DateTimeField::new('dateRetourPrevue');
        yield DateTimeField::new('dateRetour');
        yield IntegerField::new('nombreProlongations');
        yield ChoiceField::new('statut');
    }
}