# Réseau de médiathèques

Application de gestion d'un réseau de deux médiathèques : catalogue, prêts, retours, prolongations, réservations avec file d'attente, transferts entre sites, pénalités, et import de fiches depuis Open Library.

Projet réalisé pour le **PFE Challenge EMC** (Sujet 4), avec **Symfony 7.4**, **PHP 8.2** et **MySQL 8**.

## Démarrage rapide

```bash
git clone https://github.com/AmineNekhla/mediatheque.git
cd mediatheque
docker compose up --build
```

Puis ouvrir **http://localhost:8000**. Les migrations et les données de démonstration sont appliquées automatiquement. Guide complet, dépannage compris : [`INSTALL.md`](INSTALL.md).

### Comptes de démonstration (mot de passe : `password`)

| Rôle | E-mail |
|---|---|
| Administrateur | `admin@mediatheque.test` |
| Bibliothécaire | `bibliothecaire@mediatheque.test` |
| Adhérent | `adherent@mediatheque.test` (carte `MED-A0001`) |

Ces comptes n'existent que dans les données de démonstration : ne jamais réutiliser ce mot de passe.

## Niveaux atteints

| Niveau | Contenu | État |
|---|---|---|
| 1. Modéliser | 13 entités, 10 énumérations, migrations Doctrine, données de démonstration, back-office EasyAdmin | fait |
| 2. Faire respecter les règles | prêts, retours, prolongations, réservations, transferts, pénalités ; transactions et verrous ; 85 tests | fait |
| 3. Construire le produit | comptes et rôles, catalogue public, espace adhérent, guichet | fait |
| 4. S'ouvrir au monde | import de fiches par ISBN depuis Open Library, à l'unité et par lot | fait |
| 5. Livrer | Docker (une commande), `INSTALL.md`, vérifié sur un clone propre | fait |

## Ce qui fonctionne

- **Catalogue public** : recherche par titre, auteur ou ISBN ; disponibilité par exemplaire et par site ; réservation (adhérents).
- **Espace adhérent** (`/mon-espace`) : prêts en cours, prolongation, réservations (annulation), pénalités impayées.
- **Guichet** (`/biblio`) : prêt par carte et code-barres, retour (document abîmé, rendu dans l'autre site), réception des transferts, règlement des pénalités.
- **Import Open Library** (`/biblio/import`) : création de fiches à partir d'un ISBN, 10 par lot au maximum. L'API est publique : **aucune clé n'est nécessaire**.
- **Back-office** (`/admin`) : gestion du catalogue, des exemplaires, des adhérents, des formules, des sites.
- **Maintenance** : `app:maintenance` expire les réservations non retirées et marque les prêts en retard (toutes les heures dans le conteneur).

## Règles métier principales

- Emprunt refusé si l'abonnement est expiré, si une pénalité est impayée, si la limite de la formule est atteinte, ou si l'exemplaire est indisponible ou réservé à un autre adhérent.
- Prolongation gratuite, accordée seulement si personne n'attend le document, si le prêt n'est pas en retard et si le maximum de la formule n'est pas atteint.
- File d'attente : premier arrivé, premier servi. Un exemplaire réservé sur l'autre site déclenche un transfert. Retrait sous 7 jours ouvrés, puis l'exemplaire passe au suivant.
- Pénalités de retard proportionnelles au nombre de jours ; forfaits pour un document perdu ou abîmé.
- Un même exemplaire ne peut pas être prêté deux fois (transaction et verrou `SELECT ... FOR UPDATE`).

Toutes les décisions, leurs raisons et les limites sont dans [`docs/DECISIONS.md`](docs/DECISIONS.md).

## Architecture

```
src/Entity/        modèle de données (Doctrine)
src/Enum/          statuts, types et rôles
src/Service/       règles métier : PretService, ReservationService, TransfertService,
                   ImportEditionService, Calendrier, OpenLibrary/
src/Controller/    écrans (catalogue, espace, guichet, import) et Admin/ (EasyAdmin)
src/Command/       app:maintenance, app:demo-data
src/DataFixtures/  données de démonstration (Faker, graine fixe)
templates/         vues Twig
tests/             tests unitaires, d'intégration et fonctionnels
docs/              MODELE.md (modèle), DECISIONS.md (décisions et limites)
docker/, Dockerfile, compose.yaml    environnement Docker
```

Les contrôleurs ne contiennent aucune règle métier : ils appellent des services testés.

## Tests

85 tests (calendrier, règles de prêt, réservations, transferts, import Open Library, contrôle d'accès). Les appels à Open Library sont simulés : les tests n'ont pas besoin d'Internet.

Une seule fois, créer `.env.test.local` (ignoré par Git) avec un compte MySQL autorisé à créer la base de test :

```
DATABASE_URL="mysql://root:root@127.0.0.1:3306/mediatheque?serverVersion=8.0&charset=utf8mb4"
```

puis :

```bash
php bin/console --env=test doctrine:database:create
php bin/console --env=test doctrine:migrations:migrate -n
vendor/bin/phpunit
```

Chaque test s'exécute dans une transaction annulée ensuite : la base de démonstration n'est jamais touchée.

## Développement sans le conteneur `app`

Créer un `compose.override.yaml` (ignoré par Git) pour exposer MySQL à votre machine :

```yaml
services:
  database:
    ports:
      - "3306:3306"
```

puis `docker compose up -d database`, copier `.env.example` en `.env.local`, et lancer `php -d max_execution_time=300 -S 127.0.0.1:8000 -t public`.

## Ce qui manque

Pas d'e-mails de notification, pas de jours fériés dans le calcul des jours ouvrés, pas de tableau de bord statistique, image Docker en mode `dev` (démonstration). Liste complète et raisons : section « Limites connues » de [`docs/DECISIONS.md`](docs/DECISIONS.md).

## Usage de l'IA

Claude (conversations) et Antigravity (agent dans l'éditeur) ont servi à concevoir le modèle, rédiger des services, des tests et des écrans, et à diagnostiquer des erreurs. Le code a été relu, exécuté et testé avant chaque commit ; plusieurs erreurs de l'IA ont été repérées et corrigées (voir le journal dans `docs/DECISIONS.md`). Les traces complètes (conversations, prompts, sessions) sont fournies dans l'archive jointe à l'envoi du dossier.

## Documentation

- [`INSTALL.md`](INSTALL.md) : prérequis, installation, comptes, dépannage
- [`docs/MODELE.md`](docs/MODELE.md) : modèle de données (diagramme et choix)
- [`docs/DECISIONS.md`](docs/DECISIONS.md) : décisions, journal daté, limites