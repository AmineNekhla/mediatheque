<?php

namespace App\Controller\Admin;

use App\Entity\Contribution;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class ContributionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Contribution::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('personne');
        yield ChoiceField::new('role');
        yield AssociationField::new('oeuvre');
        yield AssociationField::new('edition');
    }
}