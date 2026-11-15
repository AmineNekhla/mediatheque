# Journal des décisions d'architecture et choix métier

**Projet** : gestion d'un réseau de médiathèques (Sujet 4, PFE Challenge EMC)
**Auteur** : Amine Nekhla
**Stack** : Symfony 7.4 (LTS) · PHP 8.2 · MySQL 8 · Doctrine ORM · EasyAdmin 5 · Docker

Ce document consigne les décisions prises, leurs raisons, et les limites assumées. Le modèle de données est détaillé dans `docs/MODELE.md`.

---

## 1. Réponses argumentées aux « Questions à trancher »

### Question 1 : deux éditions d'un même roman sont-elles la même œuvre ?

**Décision : oui conceptuellement, non matériellement.** Séparation inspirée du modèle bibliographique FRBR :

- `Oeuvre` : le contenu intellectuel commun (par exemple *1984* d'Orwell).
- `Edition` : une publication précise (ISBN, éditeur, langue, type de document, pagination).
- `Exemplaire` : l'objet physique, identifié par un code-barres, rattaché à une édition. C'est lui qui est prêté.

**Raisons.**

- Pas de duplication des métadonnées (auteurs, résumé) d'une édition à l'autre.
- L'usager peut chercher « le roman » sans préférence d'édition, ou viser une édition précise (gros caractères, bilingue).
- Une adaptation (un film tiré d'un roman) est une œuvre distincte ; une traduction est une édition de la même œuvre.

**Conséquence sur les réservations** : une réservation vise une œuvre (obligatoire) avec une édition facultative. Édition vide : n'importe quel exemplaire de l'œuvre convient. Édition renseignée : seuls les exemplaires de cette édition conviennent.

### Question 2 : un adhérent peut-il réserver un document disponible uniquement dans l'autre site ?

**Décision : oui.** Le réseau fonctionne comme un fonds unique partagé entre les deux médiathèques.

- L'adhérent choisit son site de retrait.
- Si un exemplaire disponible se trouve sur l'autre site, le système crée un `Transfert` (motif `RESERVATION`) et l'exemplaire passe à `EN_TRANSIT`.
- À la réception de la navette par le bibliothécaire du site de destination, l'exemplaire passe à `RESERVE` (mis de côté), la réservation à `DISPONIBLE`, et le délai de retrait démarre.
- Si la réservation est annulée pendant le trajet, l'exemplaire est remis en rayon à son arrivée et proposé au suivant de la file.

**Raison** : un réseau n'a d'intérêt que si les documents circulent entre ses sites.

**Choix d'implémentation** : l'adhérent n'est pas notifié par e-mail ; l'état de sa réservation (en attente, disponible, date limite de retrait) est visible dans « Mon espace ».

### Question 3 : combien de temps un document réservé reste-t-il mis de côté une fois revenu ?

**Décision : 7 jours ouvrés** (paramètre du service `ReservationService`, modifiable).

- Dès qu'un exemplaire est mis de côté (retour d'un prêt, arrivée d'une navette), la réservation passe à `DISPONIBLE` avec `dateExpirationRetrait = maintenant + 7 jours ouvrés` (lundi à vendredi).
- La commande `app:maintenance` (exécutée toutes les heures dans le conteneur Docker, à planifier sinon) traite les réservations dépassées :
  1. la réservation passe à `EXPIREE` ;
  2. s'il reste quelqu'un dans la file pour cette œuvre, l'exemplaire lui est attribué pour un nouveau cycle ;
  3. sinon l'exemplaire redevient `DISPONIBLE` et retourne en rayon.
- Les jours fériés ne sont pas gérés (voir limites).

**Raison** : assez long pour permettre de venir, assez court pour ne pas immobiliser les ouvrages demandés.

### Question 4 : comment représenter les auteurs multiples, traducteurs, illustrateurs ?

**Décision** : une entité `Personne` et une entité de liaison `Contribution` avec un rôle (énumération `RoleContribution`).

- Rôles : `AUTEUR`, `ILLUSTRATEUR`, `SCENARISTE`, `DESSINATEUR`, `COLORISTE`, `TRADUCTEUR`, `PREFACIER`, `REALISATEUR`, `ACTEUR`.
- Une contribution est rattachée **soit à une œuvre, soit à une édition, jamais aux deux** (règle vérifiée par une validation sur l'entité). Auteur, scénariste, dessinateur, réalisateur : niveau œuvre. Traducteur, préfacier : niveau édition.

**Raisons.** Cette structure représente fidèlement les bandes dessinées (scénariste et dessinateur distincts), les films (réalisateur, acteurs) et les traductions, et permet des recherches précises (« tout ce que X a illustré »).

### Question 5 : que faire quand Open Library renvoie des données incomplètes ou différentes de celles du catalogue ?

**Décision : le catalogue local fait foi.**

1. **Création via ISBN** : l'import enregistre directement la fiche (source `OPEN_LIBRARY`). Si l'auteur, l'éditeur ou l'année manquent, elle est marquée « à vérifier » pour contrôle par un bibliothécaire.
2. **ISBN déjà au catalogue** : jamais modifié. L'import le signale (`DEJA_PRESENT`) sans toucher aux données.
3. **ISBN inconnu d'Open Library** ou fiche sans titre : rien n'est créé (`INTROUVABLE`) ; la fiche se saisit à la main dans le back-office.
4. **Panne d'Open Library** (réseau, délai de 5 s dépassé, statut HTTP anormal, réponse illisible) : signalée (`ERREUR`), aucune écriture en base.
5. **Import par lot** : maximum 10 ISBN ; chaque ISBN est traité indépendamment, un échec n'arrête pas les autres.
6. **ISBN invalide** : la clé de contrôle (ISBN-10 et ISBN-13) est vérifiée avant tout appel réseau.
7. **Couverture** : seule l'URL en `https` est stockée (non affichée dans cette version).

---

## 2. Règles métier retenues

### 2.1 Formules d'abonnement (données de démonstration, modifiables en base)

| Formule | Prêts simultanés | Durée du prêt | Prolongations | Réservations | Pénalité par jour de retard |
|---|---|---|---|---|---|
| Standard | 3 | 21 jours | 1 | 2 | 0,50 MAD |
| Premium | 8 | 28 jours | 2 | 5 | 0,50 MAD |
| Jeune | 2 | 14 jours | 1 | 1 | 0,25 MAD |

Les montants sont stockés en **centimes** (entiers), pour éviter les erreurs d'arrondi des nombres décimaux. Monnaie : MAD.

### 2.2 Emprunt

Un emprunt est refusé si :

- l'abonnement est expiré ou inactif (le dernier jour de l'abonnement est inclus) ;
- l'adhérent a au moins une pénalité impayée ;
- il a atteint la limite de prêts simultanés de sa formule ;
- l'exemplaire n'est pas disponible, ou est mis de côté pour un autre adhérent.

Un adhérent dont la réservation est prête retire son exemplaire normalement : la réservation passe à `RETIREE`.

### 2.3 Retour

- La pénalité de retard = jours calendaires de retard × tarif de la formule. Le jour de l'échéance lui-même n'est pas en retard.
- Un document peut être rendu dans l'autre médiathèque : son site actuel est mis à jour, son site d'attache ne change pas.
- Document rendu abîmé : statut `ABIME` (retiré de la circulation) et pénalité forfaitaire de 30 MAD.
- Document déclaré perdu : statut `PERDU` et pénalité forfaitaire de 100 MAD (il n'y a pas de prix de remplacement en base).
- Au retour, la file d'attente est servie : le premier adhérent dont la demande correspond reçoit l'exemplaire (mis de côté, ou transfert si le site de retrait diffère).

### 2.4 Prolongation

- **Gratuite** : l'énoncé n'impose aucun frais.
- Refusée si le prêt est en retard, si le maximum de la formule est atteint, si l'abonnement n'est plus actif, ou **si des adhérents attendent le document** (règle de l'énoncé).
- Elle repousse l'échéance d'une durée de prêt de la formule. Le refus pour retard est un choix de ma part, non imposé par l'énoncé.

### 2.5 Réservation

- Limite de réservations actives par formule ; une seule réservation active par œuvre et par adhérent ; abonnement actif requis.
- File d'attente : premier arrivé, premier servi (date de création, puis identifiant pour départager).
- Si un exemplaire est déjà disponible dans le réseau à la réservation, il est affecté immédiatement (mis de côté sur place, ou transfert).
- Une réservation visant une édition précise ne reçoit jamais un exemplaire d'une autre édition.

### 2.6 États

- `Exemplaire` : `DISPONIBLE`, `EMPRUNTE`, `RESERVE`, `EN_TRANSIT`, `PERDU`, `ABIME`, `RETIRE` (ce dernier est prévu pour le désherbage, sans règle associée pour l'instant).
- `Pret` : `EN_COURS`, `RENDU`, `EN_RETARD`, `PERDU`. Le passage en `EN_RETARD` est fait par `app:maintenance`.
- `Reservation` : `EN_ATTENTE`, `DISPONIBLE`, `RETIREE`, `ANNULEE`, `EXPIREE`.

---

## 3. Décisions techniques

### 3.1 Stack

- **Symfony 7.4** : version LTS.
- **MySQL 8**, imposé par l'énoncé. Symfony génère du PostgreSQL par défaut : remplacé dès le premier jour.
- **Doctrine ORM et migrations versionnées** : le schéma se reconstruit de zéro avec `doctrine:migrations:migrate` (vérifié sur un clone propre).
- **EasyAdmin 5** : back-office du Niveau 1. Les champs énumérés et les associations sont déclarés explicitement dans chaque contrôleur : l'auto-détection affichait les énumérations PHP comme du texte et plantait.

### 3.2 Services, transactions et concurrence

- Les règles vivent dans `PretService`, `ReservationService`, `TransfertService` et `ImportEditionService`. Les contrôleurs lisent la requête, appellent un service testé et affichent le résultat ou le refus.
- Chaque opération s'exécute dans une **transaction** avec un **verrou pessimiste** (`SELECT ... FOR UPDATE`) sur l'exemplaire : si deux guichets visent le même exemplaire, le second attend puis voit le nouveau statut et est refusé.
- L'exemplaire porte aussi une colonne `version` (verrou optimiste Doctrine) qui détecte une modification concurrente.
- Toutes les vérifications précèdent les modifications : un refus ne laisse rien à moitié fait.
- Une aide `Transactionnel` remplace `wrapInTransaction()` : en cas d'exception celle-ci **ferme** l'EntityManager, ce qui empêcherait d'afficher une erreur propre ou de continuer un lot.
- Les services ne lisent jamais l'heure eux-mêmes : la date est un paramètre (`$maintenant`), ce qui rend les tests déterministes.

### 3.3 Sécurité (Niveau 3)

- Connexion par formulaire avec jeton CSRF ; mots de passe hachés (`auto`).
- Rôles `ROLE_ADHERENT`, `ROLE_BIBLIOTHECAIRE`, `ROLE_ADMIN`. L'administrateur hérite du bibliothécaire ; le bibliothécaire n'hérite pas d'adhérent (le personnel n'a pas de carte).
- Protection par chemin : `/admin` et `/biblio` pour le personnel, `/mon-espace` pour les adhérents.
- Un adhérent ne peut agir que sur **ses propres** prêts et réservations (contrôle de propriété dans le contrôleur) ; chaque action POST porte un jeton CSRF.
- Séparation des comptes (`Utilisateur`) et des fiches (`Adherent`) : le personnel a un compte sans carte, et un adhérent peut être inscrit au guichet sans compte en ligne.
- Nuance assumée : `/admin` est ouvert aux bibliothécaires comme aux administrateurs ; il n'y a pas de séparation de droits plus fine dans le back-office.

### 3.4 Tests

- 85 tests PHPUnit : calendrier, règle d'abonnement, validation d'ISBN (tests unitaires) ; prêts, retours, prolongations, réservations, transferts, import (tests d'intégration sur MySQL) ; contrôle d'accès et catalogue (tests fonctionnels).
- Base dédiée `mediatheque_test` ; `dama/doctrine-test-bundle` annule chaque test (transaction), donc chaque test part d'une base vide.
- Open Library est simulé (`MockHttpClient`) : tests rapides, déterministes, sans réseau.
- Un test a révélé un vrai défaut : le dernier jour d'un abonnement était refusé, car la date de fin (minuit) était comparée à l'heure courante. Corrigé en comparant des jours.

### 3.5 Livraison (Niveau 5)

- `docker compose up --build` construit l'image (PHP 8.2 + Apache), démarre MySQL 8, applique les migrations et charge les données de démonstration.
- `app:demo-data` ne charge les fixtures **que si la base est vide** : elles vident les tables, un redémarrage ne doit jamais effacer des données.
- Le conteneur tourne en `APP_ENV=dev` : choix assumé pour une démonstration locale (assets servis à la volée, fixtures disponibles). Ce n'est pas une configuration de production ; `INSTALL.md` le dit.
- Le port MySQL n'est pas publié par défaut, pour éviter les conflits avec un MySQL local (rencontré : un MySQL sous WSL interceptait le port 3306).
- `app:maintenance` s'exécute toutes les heures dans le conteneur.
- Vérifié sur un clone propre du dépôt, avec une base vide.

---

## 4. Journal daté

| Date | Décision ou événement |
|---|---|
| 2026-11-11 | **Cadrage.** Sujet 4 retenu : règles claires et testables, API Open Library sans clé, délai court. Dépôt public, squelette Symfony 7.4, PostgreSQL remplacé par MySQL 8, extension `pdo_mysql` activée. |
| 2026-11-12 | **Modèle.** 13 entités et énumérations, vocabulaire français aligné entre code et documents, migrations, données de démonstration (Faker, graine fixe), back-office EasyAdmin. Plantage des listes à champ énuméré corrigé par des champs déclarés explicitement. Conflit de port 3306 avec un MySQL sous WSL diagnostiqué. |
| 2026-11-13 | **Règles métier.** Services de prêt, réservation et transfert avec transactions et verrous ; commande de maintenance ; tests. Défaut sur le dernier jour d'abonnement corrigé après un test rouge. |
| 2026-11-14 | **Produit.** Sécurité et rôles, catalogue public, espace adhérent, guichet. Import Open Library par ISBN (validation, tolérance aux pannes, tests avec client simulé). Environnement Docker, commande `app:demo-data`, `INSTALL.md`. |
| 2026-11-15 | **Finalisation.** Installation vérifiée sur un clone propre ; README et documents alignés sur le code ; note conceptuelle ; traces d'usage de l'IA. |

---

## 5. Limites connues

- **Notifications** : pas d'e-mail ; l'état des réservations est visible dans l'espace adhérent.
- **Jours ouvrés** : seuls le samedi et le dimanche sont exclus ; les jours fériés ne sont pas gérés.
- **Pénalités** : montants forfaitaires pour un document perdu (100 MAD) ou abîmé (30 MAD) ; les pénalités de retard ne sont pas plafonnées ; le paiement est enregistré à la main au guichet.
- **Concurrence** : le verrou `SELECT ... FOR UPDATE` empêche deux prêts simultanés du même exemplaire, mais les tests exécutent les opérations l'une après l'autre ; un test avec deux connexions réellement parallèles reste à écrire.
- **Import Open Library** : chaque import crée une nouvelle œuvre (le regroupement des éditions d'une même œuvre se fait à la main) ; un même livre en ISBN-10 et en ISBN-13 serait vu comme deux ISBN différents ; la couverture n'est pas affichée.
- **Réservations** : par œuvre avec édition facultative ; pas de priorité selon la formule d'abonnement.
- **Back-office** : pas de gestion des comptes utilisateurs (créés par les données de démonstration) ; pas de tableau de bord statistique, les listes du guichet (retards, pénalités, transferts) couvrent le besoin opérationnel.
- **Docker** : image en mode `dev`, adaptée à la démonstration et non à la production.