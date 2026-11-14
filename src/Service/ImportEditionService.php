<?php

namespace App\Service;

use App\Entity\Contribution;
use App\Entity\Edition;
use App\Entity\Oeuvre;
use App\Entity\Personne;
use App\Enum\RoleContribution;
use App\Enum\SourceEdition;
use App\Enum\StatutImport;
use App\Enum\TypeDocument;
use App\Service\OpenLibrary\DonneesLivre;
use App\Service\OpenLibrary\IsbnValidateur;
use App\Service\OpenLibrary\OpenLibraryClient;
use App\Service\OpenLibrary\ServiceExterneException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Importe une édition depuis Open Library à partir d'un ISBN.
 *
 * Règles (DECISIONS.md, question 5) :
 *  - le catalogue local fait foi : un ISBN déjà présent n'est JAMAIS modifié ;
 *  - une fiche aux données incomplètes est créée avec "à vérifier" ;
 *  - une panne d'Open Library n'écrit rien en base et n'arrête pas un import par lot.
 */
class ImportEditionService
{
    use Transactionnel;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OpenLibraryClient $client,
    ) {
    }

    public function importer(string $saisie): ResultatImport
    {
        $isbn = IsbnValidateur::normaliser($saisie);
        if ($isbn === null) {
            return new ResultatImport(trim($saisie), StatutImport::INVALIDE, 'ISBN invalide (format ou clé de contrôle).');
        }

        $existante = $this->em->getRepository(Edition::class)->findOneBy(['isbn' => $isbn]);
        if ($existante !== null) {
            return new ResultatImport(
                $isbn,
                StatutImport::DEJA_PRESENT,
                sprintf('Déjà au catalogue : « %s ». Aucune donnée modifiée.', $existante->getOeuvre()->getTitre()),
                $existante,
            );
        }

        try {
            $donnees = $this->client->rechercherParIsbn($isbn);
        } catch (ServiceExterneException $e) {
            return new ResultatImport($isbn, StatutImport::ERREUR, $e->getMessage());
        }

        if ($donnees === null) {
            return new ResultatImport($isbn, StatutImport::INTROUVABLE, 'ISBN inconnu d\'Open Library : à saisir manuellement dans le back-office.');
        }

        $edition = $this->enTransaction($this->em, fn (): Edition => $this->creer($isbn, $donnees));

        $message = sprintf('Importé : « %s ».', $edition->getOeuvre()->getTitre());
        if ($edition->isAVerifier()) {
            $message .= ' Données incomplètes : fiche marquée « à vérifier ».';
        }

        return new ResultatImport($isbn, StatutImport::CREE, $message, $edition);
    }

    private function creer(string $isbn, DonneesLivre $donnees): Edition
    {
        $editeur = $donnees->editeur !== null ? mb_substr($donnees->editeur, 0, 150) : null;

        $oeuvre = (new Oeuvre())
            ->setTitre(mb_substr($donnees->titre, 0, 255))
            ->setAnneePremierePublication($donnees->annee);
        $this->em->persist($oeuvre);

        $edition = (new Edition())
            ->setOeuvre($oeuvre)
            ->setIsbn($isbn)
            ->setTypeDocument(TypeDocument::LIVRE)
            ->setEditeur($editeur)
            ->setAnneePublication($donnees->annee)
            ->setNombrePages($donnees->pages)
            ->setUrlCouverture($donnees->couverture !== null ? mb_substr($donnees->couverture, 0, 500) : null)
            ->setSource(SourceEdition::OPEN_LIBRARY)
            ->setAVerifier($donnees->auteurs === [] || $editeur === null || $donnees->annee === null);
        $this->em->persist($edition);

        foreach (array_unique($donnees->auteurs) as $nomComplet) {
            $this->em->persist(
                (new Contribution())
                    ->setPersonne($this->trouverOuCreerPersonne($nomComplet))
                    ->setRole(RoleContribution::AUTEUR)
                    ->setOeuvre($oeuvre)
            );
        }

        return $edition;
    }

    /** Réutilise une personne existante (même nom et prénom) au lieu de créer un doublon. */
    private function trouverOuCreerPersonne(string $nomComplet): Personne
    {
        $morceaux = preg_split('/\s+/', trim($nomComplet)) ?: [$nomComplet];
        $nom = mb_substr((string) array_pop($morceaux), 0, 100);
        $prenom = $morceaux === [] ? null : mb_substr(implode(' ', $morceaux), 0, 100);

        $existante = $this->em->getRepository(Personne::class)->findOneBy(['nom' => $nom, 'prenom' => $prenom]);
        if ($existante !== null) {
            return $existante;
        }

        $personne = (new Personne())->setNom($nom)->setPrenom($prenom);
        $this->em->persist($personne);

        return $personne;
    }
}
