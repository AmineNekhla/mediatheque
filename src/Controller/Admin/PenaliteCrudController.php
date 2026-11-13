<?php

namespace App\Controller\Admin;

use App\Entity\Penalite;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class PenaliteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Penalite::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('adherent');
        yield AssociationField::new('pret');
        yield ChoiceField::new('type');
        yield IntegerField::new('montantCentimes', 'Montant (centimes)');
        yield ChoiceField::new('statut');
        yield DateTimeField::new('dateCreation');
    }
}