# Journal des Décisions d'Architecture et Choix Métier

**Projet** : Gestion d'un réseau de médiathèques (Sujet 4 - PFE Challenge EMC)  
**Date initiale** : 11 Novembre 2026  
**Auteur** : Amine Nekhla  
**Stack** : Symfony 7.4 / PHP 8.2+ / MySQL 8 / Doctrine ORM / EasyAdmin  

---

## 1. Réponses argumentées aux « Questions à trancher »

### Question 1 : Deux éditions d'un même roman sont-elles la même œuvre ?

- **Décision adoptée** : **Oui conceptuellement, mais non matériellement.** Nous adoptons une séparation stricte inspirée du modèle bibliographique international **FRBR** (*Functional Requirements for Bibliographic Records*) :
  - L'entité **`Oeuvre`** porte le contenu intellectuel commun (ex. *1984* de George Orwell, sa thématique, sa date d'écriture, son résumé global).
  - L'entité **`Edition`** porte la publication spécifique (ISBN, éditeur comme Gallimard ou Folio, traducteur éventuel, date d'impression, pagination, couverture).
  - Les **`Exemplaires`** physiques sont rattachés directement à une `Edition`.
- **Justification** :
  - Cela évite la redondance des métadonnées (auteurs, genre, synopsis).
  - Pour les usagers, cela permet à la fois de rechercher "le roman *1984*" sans préférence d'édition, ou de cibler une édition particulière (ex. version bilingue, édition illustrée, gros caractères).
  - Lors des réservations, un adhérent peut réserver une édition spécifique ou accepter le premier exemplaire disponible lié à l'œuvre.

---

### Question 2 : Un adhérent peut-il réserver un document disponible uniquement dans l'autre site ?

- **Décision adoptée** : **Oui.** Le réseau fonctionne comme un ensemble unifié et solidaire entre les deux médiathèques.
- **Fonctionnement & Justification** :
  - L'adhérent choisit son **site de retrait préféré** lors de la réservation.
  - Si l'exemplaire est disponible sur l'autre site, le système émet automatiquement une demande de **`Transfert`** (navette inter-sites).
  - L'exemplaire passe au statut `EN_TRANSIT`.
  - À la réception par les bibliothécaires du site de destination, le document passe au statut `DISPONIBLE` pour le demandeur, qui est immédiatement notifié par e-mail.
  - Cela maximise l'accès au fonds documentaire (8 000 documents partagés) et valorise l'intérêt d'un réseau multi-sites.

---

### Question 3 : Combien de temps un document réservé reste-t-il mis de côté une fois revenu ?

- **Décision adoptée** : **7 jours ouvrés** (délai paramétrable dans la configuration système).
- **Fonctionnement & Justification** :
  - Dès qu'un exemplaire réservé est réceptionné (retour de prêt ou arrivée de navette), l'entité `Reservation` passe au statut `DISPONIBLE`, avec un champ `date_expiration_retrait = Maintenant + 7 jours`.
  - Un email automatique de mise à disposition est envoyé à l'adhérent.
  - **Gestion de l'expiration** : Une commande planifiée (ou événement lors du scan) vérifie quotidiennement les réservations expirées. Si le délai est dépassé :
    1. La réservation passe en `EXPIREE`.
    2. Si d'autres adhérents sont dans la file d'attente (rang suivant), le document est réassigné au prochain demandeur pour un nouveau cycle de 7 jours.
    3. Si la file est vide, l'exemplaire repasse au statut `DISPONIBLE` et est remis en rayon.
  - 7 jours offre un équilibre idéal entre confort pour l'usager et rotation dynamique des ouvrages prisés.

---

### Question 4 : Comment représenter les auteurs multiples, les traducteurs, les illustrateurs ?

- **Décision adoptée** : Création d'une table pivot / entité intermédiaire **`Contribution`** avec une énumération typée `RoleContribution`.
- **Structure** :
  - Une entité `Personne` (nom, prénom, nationalité, biographie).
  - Une entité `Contribution` reliant une `Personne` à une `Oeuvre` (ou à une `Edition` pour les travaux propres à une publication spécifique comme la traduction ou la préface).
  - Rôles gérés : `AUTEUR`, `ILLUSTRATEUR`, `SCENARISTE`, `DESSINATEUR`, `COLORISTE`, `TRADUCTEUR`, `PREFACIER`, `REALISATEUR`, `ACTEUR`.
- **Justification** :
  - Cette approche est essentielle pour modéliser fidèlement les Bandes Dessinées (ex: René Goscinny scénariste et Albert Uderzo dessinateur d'Astérix), les mangas, les anthologies et les films DVD (réalisateur vs interprètes).
  - Elle permet des recherches multicritères précises : "toutes les œuvres illustrées par X" ou "tous les livres traduits par Y".

---

### Question 5 : Que faire quand Open Library renvoie des données incomplètes ou différentes de celles du catalogue ?

- **Décision adoptée** : **Stratégie d'enrichissement progressif avec prédominance du contrôle local.**
- **Règles d'arbitrage** :
    1. **Création d'une fiche via ISBN** : l'import enregistre directement la fiche (source `OPEN_LIBRARY`) ;
     si l'auteur, l'éditeur ou l'année manquent, elle est marquée « à vérifier » pour contrôle par un
     bibliothécaire. Un ISBN déjà au catalogue n'est jamais modifié. Un import par lot traite chaque ISBN
     indépendamment (maximum 10) ; une panne d'Open Library est signalée sans rien écrire en base.
  2. **Données manquantes (ex: genre littéraire, cote Dewey)** : L'entité `Edition` est sauvegardée avec un marqueur `a_verifier = true` pour signaler aux bibliothécaires les fiches nécessitant une indexation manuelle.
  3. **Différence sur un document existant** : Les données déjà saisies et validées par le catalogue local font toujours foi. L'API distante n'écrase jamais automatiquement les champs locaux sans action explicite de l'administrateur.
  4. **Fallback & Couvertures** : Si la couverture Open Library est indisponible, une image générique par défaut selon le type de document (`LIVRE`, `BD`, `DVD`) est assignée.

---

## 2. Décisions Techniques et Architecturales

### 2.1 Stack & Framework
- **Symfony 7.4** : Version moderne LTS / courante garantissant le support des attributs PHP 8.2+, de Symfony Flex et de la gestion fine des formulaires et services.
- **MySQL 8.0** : Moteur de base de données relationnel imposé, supportant les clés étrangères, contraintes d'unicité, JSON natif et transactions ACID requises pour l'intégrité des prêts/réservations.
- **Doctrine ORM 3 & Migrations** : Gestion du schéma via migrations versionnées pour une traçabilité totale des évolutions de base de données.
- **EasyAdmin Bundle 4** : Interface back-office rapide et robuste pour le niveau 1 et l'administration quotidienne des bibliothécaires.

### 2.2 Stratégie des statuts (State Machines)
- Les cycles de vie d'un `Pret` (`EN_COURS` -> `RENDU` / `EN_RETARD` / `PERDU`) et d'un `Exemplaire` (`DISPONIBLE` -> `EMPRUNTE` -> `RESERVE` -> `EN_TRANSIT`) sont isolés dans des services métier dédiés (`LoanService`, `ReservationService`, `TransferService`) avec transactions explicites pour éviter tout état incohérent ou concurrence d'accès (race condition lors de réservations simultanées).

### 2.3 Gestion des rôles et sécurisation (Niveau 3)
- `ROLE_USER` / `ROLE_ADHERENT` : Consultation du catalogue public, état de ses emprunts personnels, pose de réservations.
- `ROLE_BIBLIOTHECAIRE` : Gestion des prêts/retours au guichet, validation des réceptions de transferts, saisie d'exemplaires.
- `ROLE_ADMIN` : Paramétrage des formules d'abonnements, gestion des comptes utilisateurs, statistiques globales.



## 2026-11-13 — Règles métier (Niveau 2)

- **Transactions et verrous** : chaque opération (emprunt, retour, prolongation, réservation, transfert)
  s'exécute dans une transaction avec verrou pessimiste (`SELECT ... FOR UPDATE`) sur l'exemplaire.
  Toutes les vérifications précèdent les modifications.
- **Emprunt** : refusé si abonnement inactif, pénalités impayées, limite de prêts atteinte,
  ou exemplaire indisponible / réservé pour un autre adhérent.
- **Prolongation** : refusée si le prêt est en retard, si le maximum de la formule est atteint,
  ou si des adhérents attendent le document. Le refus en cas de retard est un choix de ma part
  (non imposé par l'énoncé).
- **File d'attente** : premier arrivé, premier servi (date puis identifiant). Une réservation vise
  une œuvre, avec une édition facultative.
- **Retrait** : 7 jours ouvrés (lundi à vendredi, jours fériés non gérés), paramétrable.
- **Pénalités** : retard = jours calendaires de retard × tarif de la formule ; perte = 100 MAD
  forfaitaire ; dégradation = 30 MAD forfaitaire. Il n'y a pas de prix de remplacement en base, donc
  les montants sont forfaitaires et les pénalités de retard ne sont pas plafonnées.
- **Maintenance** : la commande `app:maintenance` expire les réservations et marque les retards ;
  elle est prévue pour une exécution quotidienne (cron).
- **Limite connue** : les notifications par e-mail ne sont pas implémentées (remplacées par l'état
  visible dans l'espace adhérent).


  ## 2026-11-13 — Sécurité (Niveau 3)

- **Authentification** : formulaire de connexion Symfony avec protection CSRF, mots de passe hachés (`auto`).
- **Rôles** : `ROLE_ADHERENT`, `ROLE_BIBLIOTHECAIRE`, `ROLE_ADMIN`. L'administrateur hérite du
  bibliothécaire. Le bibliothécaire n'hérite pas d'adhérent : le personnel n'a pas de carte.
- **Protection par chemin** : `/admin` et `/biblio` pour le personnel, `/mon-espace` pour les adhérents.
- **Compte de démonstration** : mot de passe `password` (données de démo uniquement, jamais en production).


---

## 3. Limites connues

- **Import Open Library** : chaque import crée une nouvelle œuvre (le regroupement des éditions d'une même
  œuvre se fait à la main dans le back-office) ; un même livre en ISBN-10 et en ISBN-13 serait vu comme deux
  ISBN différents ; la couverture n'est pas affichée (seule l'URL est stockée).
- **Prolongation gratuite** : l'énoncé n'impose aucun frais ; elle est refusée si le prêt est en retard,
  si le maximum de la formule est atteint, ou si des adhérents attendent le document.
- **Jours ouvrés** : seuls le samedi et le dimanche sont exclus ; les jours fériés ne sont pas gérés.
- **Notifications** : pas d'envoi d'e-mail ; l'état des réservations est visible dans l'espace adhérent.
- **Pénalités** : montants forfaitaires pour un document perdu (100 MAD) ou abîmé (30 MAD), faute de prix de
  remplacement en base ; les pénalités de retard ne sont pas plafonnées. Le paiement est enregistré
  manuellement au guichet.
- **Tests de concurrence** : le verrou `SELECT ... FOR UPDATE` empêche deux prêts simultanés du même
  exemplaire, mais les tests exécutent les opérations l'une après l'autre ; un test avec deux connexions
  en parallèle reste à écrire.
- **Back-office** : le CRUD des comptes utilisateurs n'est pas fourni (création par fixtures).

## 2026-11-14 — Livraison (Niveau 5)

- **Une commande** : `docker compose up --build` construit l'image (PHP 8.2 + Apache), démarre MySQL 8,
  applique les migrations et charge les données de démonstration.
- **Données de démonstration protégées** : `app:demo-data` ne charge les fixtures que si la base est vide,
  car elles vident les tables ; un redémarrage ne détruit donc jamais de données.
- **Environnement `dev` dans le conteneur** : choix assumé pour une démonstration (assets servis à la volée,
  outil de chargement des fixtures disponible). Ce n'est pas une configuration de production ; cela
  est signalé dans `INSTALL.md`.
- **Port MySQL non publié** par défaut, pour éviter les conflits avec un MySQL local (rencontré pendant le
  développement : un MySQL sous WSL interceptait le port 3306).
- **Maintenance planifiée** : `app:maintenance` s'exécute toutes les heures dans le conteneur
  (expiration des réservations, prêts en retard).