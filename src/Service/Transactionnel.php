<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Exécute une opération métier dans une transaction SQL.
 *
 * On évite EntityManager::wrapInTransaction() car, en cas d'exception, il FERME
 * l'EntityManager : on ne pourrait plus l'utiliser ensuite (afficher une erreur
 * propre, continuer un test...). Ici on annule la transaction et on relance l'exception.
 * Toutes les vérifications se font AVANT de modifier les entités.
 */
trait Transactionnel
{
    private function enTransaction(EntityManagerInterface $em, callable $operation): mixed
    {
        $connexion = $em->getConnection();
        $connexion->beginTransaction();

        try {
            $resultat = $operation();
            $em->flush();
            $connexion->commit();

            return $resultat;
        } catch (\Throwable $e) {
            $connexion->rollBack();

            throw $e;
        }
    }
}