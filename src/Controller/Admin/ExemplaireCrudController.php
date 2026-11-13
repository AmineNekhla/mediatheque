<?php

namespace App\Controller\Admin;

use App\Entity\Exemplaire;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ExemplaireCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Exemplaire::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('codeBarres');
        yield AssociationField::new('edition');
        yield AssociationField::new('siteAttache');
        yield AssociationField::new('siteActuel');
        yield ChoiceField::new('statut');
    }
}