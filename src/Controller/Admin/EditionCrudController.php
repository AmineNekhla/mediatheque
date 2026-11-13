<?php

namespace App\Controller\Admin;

use App\Entity\Edition;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class EditionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Edition::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('oeuvre');
        yield TextField::new('isbn');
        yield ChoiceField::new('typeDocument');
        yield TextField::new('editeur');
        yield TextField::new('langue');
        yield IntegerField::new('anneePublication');
        yield IntegerField::new('nombrePages');
        yield TextField::new('urlCouverture')->hideOnIndex();
        yield ChoiceField::new('source');
        yield BooleanField::new('aVerifier', 'À vérifier');
    }
}