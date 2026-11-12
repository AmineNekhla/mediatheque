<?php

namespace App\DataFixtures;

use App\Entity\Adherent;
use App\Entity\Contribution;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\FormuleAbonnement;
use App\Entity\Oeuvre;
use App\Entity\Penalite;
use App\Entity\Personne;
use App\Entity\Pret;
use App\Entity\Reservation;
use App\Entity\Site;
use App\Entity\Transfert;
use App\Entity\Utilisateur;
use App\Enum\MotifTransfert;
use App\Enum\RoleContribution;
use App\Enum\StatutExemplaire;
use App\Enum\StatutPenalite;
use App\Enum\StatutPret;
use App\Enum\StatutReservation;
use App\Enum\TypeDocument;
use App\Enum\TypePenalite;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $om): void
    {
        $faker = Factory::create('fr_FR');
        $faker->seed(2026); // données reproductibles
        $today = new \DateTimeImmutable('today');

        // ---------- Sites ----------
        $sites = [];
        foreach ([
            ['Médiathèque Centrale', '12 avenue Mohammed V, Casablanca'],
            ['Médiathèque Annexe', '45 rue Al Massira, Casablanca'],
        ] as [$nom, $adresse]) {
            $s = (new Site())->setNom($nom)->setAdresse($adresse);
            $om->persist($s);
            $sites[] = $s;
        }

        // ---------- Formules d'abonnement (montants en centimes) ----------
        $formules = [];
        foreach ([
            ['Standard', 3, 21, 1, 2, 50, 15000],
            ['Premium', 8, 28, 2, 5, 50, 30000],
            ['Jeune', 2, 14, 1, 1, 25, 5000],
        ] as [$nom, $prets, $duree, $prol, $res, $pen, $prix]) {
            $f = (new FormuleAbonnement())
                ->setNom($nom)->setPretsMax($prets)->setDureePretJours($duree)
                ->setProlongationsMax($prol)->setReservationsMax($res)
                ->setPenaliteCentimesParJour($pen)->setPrixCentimes($prix);
            $om->persist($f);
            $formules[] = $f;
        }

        // ---------- Personnes ----------
        $personnes = [];
        for ($i = 0; $i < 40; $i++) {
            $p = (new Personne())
                ->setNom($faker->lastName())
                ->setPrenom($faker->firstName())
                ->setNationalite($faker->randomElement(['Marocaine', 'Française', 'Algérienne', 'Britannique', 'Américaine', 'Japonaise']));
            $om->persist($p);
            $personnes[] = $p;
        }

        $contribuer = function (Personne $p, RoleContribution $r, ?Oeuvre $o, ?Edition $e) use ($om): void {
            $om->persist((new Contribution())->setPersonne($p)->setRole($r)->setOeuvre($o)->setEdition($e));
        };

        // ---------- Catalogue : œuvres, éditions, exemplaires ----------
        $noms = ['Le secret', 'La maison', 'Le voyage', 'La dernière lettre', 'Le jardin', "L'ombre", 'Le silence', 'La traversée', 'Le gardien', 'La mémoire', 'Le chant', 'La route', 'Le phare', 'La promesse', 'Le marchand'];
        $villes = ['Tanger', 'Fès', 'Rabat', 'Marrakech', 'Casablanca', 'Tétouan', 'Agadir', 'Meknès', 'Safi', 'Nador'];
        $editeurs = ['Gallimard', 'Hachette', 'Flammarion', 'Dargaud', 'Casterman', 'Actes Sud', 'Le Seuil', 'Albin Michel', 'Eddif', 'La Croisée des Chemins'];
        $types = [TypeDocument::LIVRE, TypeDocument::LIVRE, TypeDocument::LIVRE, TypeDocument::BD, TypeDocument::BD, TypeDocument::DVD];

        $exemplaires = [];
        $compteur = 1;

        for ($i = 0; $i < 120; $i++) {
            $type = $faker->randomElement($types);
            $langueOrig = $faker->randomElement(['fr', 'fr', 'en', 'ar']);

            $oeuvre = (new Oeuvre())
                ->setTitre($faker->randomElement($noms) . ' de ' . $faker->randomElement($villes))
                ->setLangueOriginale($langueOrig)
                ->setAnneePremierePublication($faker->numberBetween(1950, 2024))
                ->setResume('Résumé à compléter par les bibliothécaires.');
            $om->persist($oeuvre);

            // Contributions au niveau de l'œuvre
            if ($type === TypeDocument::LIVRE) {
                $contribuer($faker->randomElement($personnes), RoleContribution::AUTEUR, $oeuvre, null);
            } elseif ($type === TypeDocument::BD) {
                $contribuer($faker->randomElement($personnes), RoleContribution::SCENARISTE, $oeuvre, null);
                $contribuer($faker->randomElement($personnes), RoleContribution::DESSINATEUR, $oeuvre, null);
            } else {
                $contribuer($faker->randomElement($personnes), RoleContribution::REALISATEUR, $oeuvre, null);
                $contribuer($faker->randomElement($personnes), RoleContribution::ACTEUR, $oeuvre, null);
                $contribuer($faker->randomElement($personnes), RoleContribution::ACTEUR, $oeuvre, null);
            }

            $nbEditions = $faker->numberBetween(1, 2);
            for ($j = 0; $j < $nbEditions; $j++) {
                $langue = $faker->randomElement(['fr', 'fr', 'fr', 'en']);
                $edition = (new Edition())
                    ->setOeuvre($oeuvre)
                    ->setTypeDocument($type)
                    ->setEditeur($faker->randomElement($editeurs))
                    ->setLangue($langue)
                    ->setAnneePublication($faker->numberBetween($oeuvre->getAnneePremierePublication(), 2025))
                    ->setIsbn($type === TypeDocument::DVD ? null : $faker->unique()->isbn13())
                    ->setNombrePages($type === TypeDocument::DVD ? null : $faker->numberBetween(48, 600))
                    ->setAVerifier($faker->boolean(5));
                $om->persist($edition);

                // Traducteur au niveau de l'édition quand la langue diffère de l'originale
                if ($type !== TypeDocument::DVD && $langue !== $langueOrig) {
                    $contribuer($faker->randomElement($personnes), RoleContribution::TRADUCTEUR, null, $edition);
                }

                $nbCopies = $faker->numberBetween(1, 3);
                for ($k = 0; $k < $nbCopies; $k++) {
                    $site = $faker->randomElement($sites);
                    $ex = (new Exemplaire())
                        ->setEdition($edition)
                        ->setSiteAttache($site)
                        ->setSiteActuel($site)
                        ->setCodeBarres(sprintf('MED-%05d', $compteur++))
                        ->setStatut(StatutExemplaire::DISPONIBLE);
                    $om->persist($ex);
                    $exemplaires[] = $ex;
                }
            }
        }

        // ---------- Comptes (mot de passe de démo : "password") ----------
        $creerUtilisateur = function (string $email, array $roles) use ($om): Utilisateur {
            $u = (new Utilisateur())->setEmail($email)->setRoles($roles);
            $u->setMotDePasse($this->hasher->hashPassword($u, 'password'));
            $om->persist($u);
            return $u;
        };
        $creerUtilisateur('admin@mediatheque.test', ['ROLE_ADMIN']);
        $creerUtilisateur('bibliothecaire@mediatheque.test', ['ROLE_BIBLIOTHECAIRE']);
        $compteAdherent = $creerUtilisateur('adherent@mediatheque.test', ['ROLE_ADHERENT']);

        // ---------- Adhérents (les 3 derniers ont un abonnement expiré) ----------
        $adherents = [];
        for ($i = 0; $i < 60; $i++) {
            $expire = $i >= 57;
            $debut = $expire ? $today->modify('-400 days') : $today->modify('-' . $faker->numberBetween(10, 300) . ' days');
            $fin = $expire ? $today->modify('-35 days') : $debut->modify('+1 year');

            $a = (new Adherent())
                ->setNumeroCarte(sprintf('MED-A%04d', $i + 1))
                ->setNom($i === 0 ? 'Demo' : $faker->lastName())
                ->setPrenom($i === 0 ? 'Adherent' : $faker->firstName())
                ->setFormule($i === 0 ? $formules[0] : $faker->randomElement($formules))
                ->setSite($faker->randomElement($sites))
                ->setDebutAbonnement($debut)
                ->setFinAbonnement($fin);
            if ($i === 0) {
                $a->setUtilisateur($compteAdherent);
            }
            $om->persist($a);
            $adherents[] = $a;
        }

        // ---------- Historique : 80 prêts rendus (20 % en retard, avec pénalité) ----------
        for ($i = 0; $i < 80; $i++) {
            $ex = $faker->randomElement($exemplaires);
            $idx = $faker->numberBetween(0, 56);
            $ad = $adherents[$idx];
            $duree = $ad->getFormule()->getDureePretJours();

            $emprunt = $today->modify('-' . $faker->numberBetween(60, 250) . ' days');
            $prevue = $emprunt->modify("+{$duree} days");
            $enRetard = $faker->boolean(20);
            $joursRetard = $enRetard ? $faker->numberBetween(1, 15) : 0;
            $retour = $enRetard
                ? $prevue->modify("+{$joursRetard} days")
                : $emprunt->modify('+' . $faker->numberBetween(1, $duree) . ' days');

            $pret = (new Pret())
                ->setExemplaire($ex)->setAdherent($ad)
                ->setDateEmprunt($emprunt)->setDateRetourPrevue($prevue)->setDateRetour($retour)
                ->setStatut(StatutPret::RENDU);
            $om->persist($pret);

            if ($enRetard) {
                $om->persist((new Penalite())
                    ->setAdherent($ad)->setPret($pret)->setType(TypePenalite::RETARD)
                    ->setMontantCentimes($joursRetard * $ad->getFormule()->getPenaliteCentimesParJour())
                    ->setStatut($idx >= 50 ? StatutPenalite::IMPAYEE : StatutPenalite::PAYEE)
                    ->setDateCreation($retour));
            }
        }

        // ---------- Prêts en cours (25) et en retard (8) ----------
        $disponibles = $faker->shuffle($exemplaires);
        $empruntes = [];

        for ($i = 0; $i < 25; $i++) {
            $ex = array_pop($disponibles);
            $ad = $adherents[$i];
            $duree = $ad->getFormule()->getDureePretJours();
            $emprunt = $today->modify('-' . $faker->numberBetween(1, 10) . ' days');

            $om->persist((new Pret())
                ->setExemplaire($ex)->setAdherent($ad)
                ->setDateEmprunt($emprunt)->setDateRetourPrevue($emprunt->modify("+{$duree} days"))
                ->setStatut(StatutPret::EN_COURS));
            $ex->setStatut(StatutExemplaire::EMPRUNTE);
            $empruntes[] = $ex;
        }

        for ($i = 25; $i < 33; $i++) {
            $ex = array_pop($disponibles);
            $ad = $adherents[$i];
            $duree = $ad->getFormule()->getDureePretJours();
            $emprunt = $today->modify('-' . ($duree + $faker->numberBetween(3, 20)) . ' days');

            $om->persist((new Pret())
                ->setExemplaire($ex)->setAdherent($ad)
                ->setDateEmprunt($emprunt)->setDateRetourPrevue($emprunt->modify("+{$duree} days"))
                ->setStatut(StatutPret::EN_RETARD));
            $ex->setStatut(StatutExemplaire::EMPRUNTE);
        }

        // ---------- Réservations en attente sur des documents empruntés ----------
        for ($i = 0; $i < 6; $i++) {
            $ex = $empruntes[$i];
            $om->persist((new Reservation())
                ->setAdherent($adherents[40 + $i])
                ->setOeuvre($ex->getEdition()->getOeuvre())
                ->setEdition($i % 2 === 1 ? $ex->getEdition() : null)
                ->setSiteRetrait($faker->randomElement($sites))
                ->setDateCreation($today->modify('-' . $faker->numberBetween(1, 5) . ' days'))
                ->setStatut(StatutReservation::EN_ATTENTE));
        }

        // ---------- Un transfert en cours ----------
        $ex = array_pop($disponibles);
        $origine = $ex->getSiteActuel();
        $destination = $sites[0] === $origine ? $sites[1] : $sites[0];
        $ex->setStatut(StatutExemplaire::EN_TRANSIT);
        $om->persist((new Transfert())
            ->setExemplaire($ex)->setSiteOrigine($origine)->setSiteDestination($destination)
            ->setMotif(MotifTransfert::REEQUILIBRAGE)
            ->setDateDemande($today->modify('-1 day')));

        $om->flush();
    }
}