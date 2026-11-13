# Médiathèques: PFE Challenge EMC

Subject 4: library network management (Symfony 7.4, MySQL 8).  
Status: in progress (Level 1).

---

## 📖 Présentation du projet

Application web de gestion d'un réseau de deux médiathèques de quartier gérant environ 8 000 documents (livres, bandes dessinées, DVD) et 1 500 adhérents. Développée dans le cadre du PFE Challenge organisé par **Education Media Company** (Technopark Souss Massa, Agadir).

### Objectifs par niveaux

- [x] **Socle technique** : Initialisation du projet Symfony 7.4, configuration MySQL 8, structure des dossiers et documentation.
- [ ] **Niveau 1 — Modéliser** : Modèle de données du domaine (œuvres, éditions, exemplaires, adhérents, abonnements, prêts, réservations), migrations Doctrine, fixtures réalistes et interface d'administration (EasyAdmin).
- [ ] **Niveau 2 — Faire respecter les règles** : Règles de prêt selon abonnements, file d'attente FIFO des réservations, gestion des prolongations et pénalités de retard, tests automatisés (PHPUnit).
- [ ] **Niveau 3 — En faire un produit** : Comptes et rôles (adhérent, bibliothécaire, admin), catalogue public avec recherche, espace adhérent et statistiques d'emprunt.
- [ ] **Niveau 4 — S'ouvrir au monde** : Intégration de l'API Open Library (recherche et importation de fiches par ISBN).
- [ ] **Niveau 5 — Livrer** : Environnement conteneurisé Docker Compose prêt à l'emploi et guide d'installation `INSTALL.md`.

---

## 🛠️ Stack technique

- **Langage / Framework** : PHP 8.2+ / Symfony 7.4
- **Base de données** : MySQL 8.0 (Doctrine ORM 3 & Doctrine Migrations)
- **Back-Office / Administration** : EasyAdmin 4
- **Tests** : PHPUnit 10
- **Conteneurisation** : Docker & Docker Compose

---

## 📚 Documentation

- [Modèle de données (docs/MODELE.md)](docs/MODELE.md) : Diagramme des entités (Mermaid ERD), dictionnaire des données et justification des relations.
- [Journal des décisions (docs/DECISIONS.md)](docs/DECISIONS.md) : Réponses argumentées aux questions à trancher et décisions d'architecture.

---

## 🚀 Installation locale

### Prérequis
- PHP >= 8.2 (extensions `pdo_mysql`, `intl`, `mbstring`, `curl`)
- Composer >= 2.x
- MySQL 8.0 ou Docker

### Démarrage rapide

```bash
# 1. Cloner le dépôt
git clone <url-du-depot>
cd mediatheque

# 2. Configurer les variables d'environnement
cp .env.example .env.local

# 3. Lancer la base de données MySQL via Docker (si nécessaire)
docker compose up -d

# 4. Installer les dépendances PHP
composer install

# 5. Créer la base de données et appliquer les migrations
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n

# 6. Charger les données de démonstration
php bin/console doctrine:fixtures:load -n

# 7. Lancer le serveur de développement Symfony
symfony server:start
# ou
php -S 127.0.0.1:8000 -t public
```

## Tests

Une fois : créer `.env.test.local` (voir `.env.example`), puis
`php bin/console --env=test doctrine:database:create` et `... doctrine:migrations:migrate -n`.
Lancer : `vendor\bin\phpunit` (51 tests : calendrier, règles de prêt, réservations, transferts).