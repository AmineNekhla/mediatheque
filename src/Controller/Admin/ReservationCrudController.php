<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class ReservationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Reservation::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('adherent');
        yield AssociationField::new('oeuvre');
        yield AssociationField::new('edition');
        yield AssociationField::new('siteRetrait');
        yield AssociationField::new('exemplaireReserve');
        yield DateTimeField::new('dateCreation');
        yield DateTimeField::new('dateExpirationRetrait');
        yield ChoiceField::new('statut');
    }
}