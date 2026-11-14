# Installation — Réseau de médiathèques

Application Symfony 7.4 / PHP 8.2 / MySQL 8, livrée avec un environnement Docker complet :
**une seule commande** construit l'image, démarre MySQL, applique les migrations et charge les données de démonstration.

## 1. Prérequis

| Outil | Version | Vérification |
|---|---|---|
| Docker (Docker Desktop sous Windows et macOS) | 24 ou plus, Compose v2 | `docker --version` et `docker compose version` |
| Git | toute version récente | `git --version` |
| Port **8000** libre sur votre machine | | voir le dépannage pour en changer |
| Connexion Internet | pour la première construction (images, dépendances) | |

Rien d'autre n'est à installer : ni PHP, ni Composer, ni MySQL.

## 2. Installation

```bash
git clone https://github.com/AmineNekhla/mediatheque.git
cd mediatheque
docker compose up --build
```

- La **première** exécution prend de 3 à 10 minutes (téléchargement des images, installation des dépendances).
  Les suivantes démarrent en quelques secondes.
- Attendez la ligne `[entrypoint] Prêt : http://localhost:8000`.
- Ouvrez **http://localhost:8000**.

Pour lancer en arrière-plan : `docker compose up --build -d`, puis `docker compose logs -f app` pour suivre le démarrage.

## 3. Comptes de démonstration

Mot de passe de tous les comptes : `password`

| Rôle | E-mail | Accès |
|---|---|---|
| Administrateur | `admin@mediatheque.test` | tout, dont `/admin` |
| Bibliothécaire | `bibliothecaire@mediatheque.test` | `/biblio` (guichet), `/biblio/import`, `/admin` |
| Adhérent | `adherent@mediatheque.test` (carte `MED-A0001`) | `/mon-espace`, réservations |

Ces comptes et ce mot de passe n'existent que pour la démonstration.

Pistes pour tester : au guichet, prêtez un exemplaire à la carte `MED-A0002` avec un code-barres (`MED-00001`...) ;
les cartes `MED-A0058` à `MED-A0060` ont un abonnement expiré ; les cartes `MED-A0051` à `MED-A0057` peuvent
avoir des pénalités impayées qui bloquent l'emprunt.

## 4. Commandes utiles

```bash
docker compose ps                          # état des services
docker compose logs -f app                 # journal de l'application
docker compose stop                        # arrêter (les données sont conservées)
docker compose up -d                       # redémarrer
docker compose down -v                     # TOUT supprimer, base comprise (remise à zéro)
docker compose exec app php bin/console app:maintenance   # expirer les réservations, marquer les retards
docker compose exec app php bin/console list app          # commandes de l'application
```

La tâche `app:maintenance` s'exécute aussi automatiquement toutes les heures dans le conteneur
(variable `MAINTENANCE_INTERVAL`, en secondes).

## 5. Variables facultatives

À placer dans un fichier `.env` à côté de `compose.yaml` ou à passer devant la commande (voir `.env.example`) :

| Variable | Défaut | Rôle |
|---|---|---|
| `APP_PORT` | `8000` | port web sur votre machine |
| `LOAD_DEMO_DATA` | `1` | `0` pour démarrer avec une base vide |
| `MYSQL_*` | `app` / `root` | identifiants MySQL du conteneur |

Les données de démonstration ne sont chargées que si la base est vide : redémarrer ne les écrase jamais.

## 6. Lancer les tests (facultatif)

```bash
docker compose exec database mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS mediatheque_test"
docker compose exec -e APP_ENV=test -e DATABASE_URL="mysql://root:root@database:3306/mediatheque?serverVersion=8.0&charset=utf8mb4" app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -e APP_ENV=test -e DATABASE_URL="mysql://root:root@database:3306/mediatheque?serverVersion=8.0&charset=utf8mb4" app vendor/bin/phpunit
```

Résultat attendu : `OK (85 tests, ...)`. Les tests utilisent une base dédiée (`mediatheque_test`) et annulent leurs
modifications : les données de démonstration ne sont jamais touchées.

## 7. Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| `port is already allocated` ou `address already in use` | le port 8000 est pris | `APP_PORT=8080 docker compose up --build` (PowerShell : `$env:APP_PORT=8080; docker compose up --build`) |
| `Cannot connect to the Docker daemon` | Docker n'est pas démarré | lancer Docker Desktop, attendre qu'il soit prêt |
| `entrypoint.sh: not found` ou `bad interpreter` | fins de ligne Windows (CRLF) dans le script | `git config core.autocrlf false`, recloner ; le `Dockerfile` corrige aussi ce cas |
| Le démarrage reste sur « Attente de la base de données » | MySQL n'a pas fini son initialisation (1re fois : 30 à 60 s) | patienter ; sinon `docker compose logs database` |
| `Access denied` pour MySQL après un changement de mot de passe | le volume garde les anciens identifiants | `docker compose down -v` puis relancer |
| Échec pendant `composer install` ou `importmap:install` | pas d'accès Internet ou pare-feu | réessayer avec un accès Internet ; proxy : voir la documentation Docker |
| Page blanche ou erreur 500 | journal de l'application | `docker compose logs app` |
| L'import Open Library indique `ERREUR` | Internet indisponible depuis le conteneur, ou service lent | réessayer plus tard : une panne n'écrit rien en base |
| Tout réinitialiser | | `docker compose down -v && docker compose up --build` |

## 8. Notes sur l'environnement

- Le conteneur tourne en `APP_ENV=dev` : adapté à une démonstration locale (barre de débogage, assets servis à la volée),
  **pas à une mise en production**. Une mise en production demanderait `APP_ENV=prod`, un vrai `APP_SECRET`,
  des identifiants MySQL forts, la compilation des assets (`asset-map:compile`) et HTTPS.
- Le port MySQL n'est pas publié sur votre machine : il n'entre donc pas en conflit avec un MySQL local.
  Pour s'y connecter depuis l'extérieur, créez un fichier `compose.override.yaml` publiant `3306` (voir README).
