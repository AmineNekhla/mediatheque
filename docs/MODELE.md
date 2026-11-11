# Modèle de données — Réseau de médiathèques

**Projet** : PFE Challenge EMC, Sujet 4
**Auteur** : Amine Nekhla
**Date** : 11 novembre 2026

Ce document est la référence pour les entités Doctrine. Les noms (entités, statuts, rôles) sont identiques à ceux de `docs/DECISIONS.md`.

## 1. Diagramme

```mermaid
erDiagram
    SITE ||--o{ EXEMPLAIRE : "site_attache"
    SITE ||--o{ EXEMPLAIRE : "site_actuel"
    SITE ||--o{ ADHERENT : "site_inscription"
    SITE ||--o{ RESERVATION : "site_retrait"
    SITE ||--o{ TRANSFERT : "origine / destination"

    OEUVRE ||--o{ EDITION : "possede"
    EDITION ||--o{ EXEMPLAIRE : "possede"
    OEUVRE ||--o{ CONTRIBUTION : "credite"
    EDITION ||--o{ CONTRIBUTION : "credite"
    PERSONNE ||--o{ CONTRIBUTION : "participe"

    FORMULE_ABONNEMENT ||--o{ ADHERENT : "souscrit"
    UTILISATEUR ||--o| ADHERENT : "a pour profil"

    ADHERENT ||--o{ PRET : "emprunte"
    EXEMPLAIRE ||--o{ PRET : "est prete"
    ADHERENT ||--o{ RESERVATION : "reserve"
    OEUVRE ||--o{ RESERVATION : "est reservee"
    EDITION |o--o{ RESERVATION : "edition souhaitee (optionnel)"
    EXEMPLAIRE |o--o{ RESERVATION : "exemplaire mis de cote"
    EXEMPLAIRE ||--o{ TRANSFERT : "est transfere"
    RESERVATION |o--o{ TRANSFERT : "declenche"
    ADHERENT ||--o{ PENALITE : "doit"
    PRET ||--o{ PENALITE : "genere"

    SITE {
        int id PK
        string nom UK
        string adresse
    }
    OEUVRE {
        int id PK
        string titre
        string langue_originale
        int annee_premiere_publication
        text resume
    }
    EDITION {
        int id PK
        int oeuvre_id FK
        string isbn UK "nullable"
        enum type_document "LIVRE, BD, DVD"
        string editeur
        string langue
        int annee_publication
        int nombre_pages "nullable"
        string url_couverture "nullable"
        enum source "MANUELLE, OPEN_LIBRARY"
        bool a_verifier
    }
    PERSONNE {
        int id PK
        string nom
        string prenom
        string nationalite "nullable"
        text biographie "nullable"
    }
    CONTRIBUTION {
        int id PK
        int personne_id FK
        int oeuvre_id FK "nullable"
        int edition_id FK "nullable"
        enum role "AUTEUR, ILLUSTRATEUR, SCENARISTE, DESSINATEUR, COLORISTE, TRADUCTEUR, PREFACIER, REALISATEUR, ACTEUR"
    }
    EXEMPLAIRE {
        int id PK
        int edition_id FK
        int site_attache_id FK
        int site_actuel_id FK
        string code_barres UK
        enum statut "DISPONIBLE, EMPRUNTE, RESERVE, EN_TRANSIT, PERDU, ABIME, RETIRE"
        int version "verrou optimiste"
    }
    FORMULE_ABONNEMENT {
        int id PK
        string nom UK
        int prets_max
        int duree_pret_jours
        int prolongations_max
        int reservations_max
        int penalite_centimes_par_jour
        int prix_centimes
    }
    UTILISATEUR {
        int id PK
        string email UK
        string mot_de_passe
        json roles
    }
    ADHERENT {
        int id PK
        int utilisateur_id FK "nullable, unique"
        int formule_id FK
        int site_id FK
        string numero_carte UK
        string nom
        string prenom
        date debut_abonnement
        date fin_abonnement
    }
    PRET {
        int id PK
        int exemplaire_id FK
        int adherent_id FK
        datetime date_emprunt
        datetime date_retour_prevue
        datetime date_retour "null = en cours"
        int nombre_prolongations
        enum statut "EN_COURS, RENDU, EN_RETARD, PERDU"
    }
    RESERVATION {
        int id PK
        int adherent_id FK
        int oeuvre_id FK
        int edition_id FK "nullable"
        int site_retrait_id FK
        int exemplaire_reserve_id FK "nullable"
        datetime date_creation
        datetime date_expiration_retrait "nullable"
        enum statut "EN_ATTENTE, DISPONIBLE, RETIREE, ANNULEE, EXPIREE"
    }
    PENALITE {
        int id PK
        int adherent_id FK
        int pret_id FK
        enum type "RETARD, PERDU, ABIME"
        int montant_centimes
        enum statut "IMPAYEE, PAYEE, ANNULEE"
        datetime date_creation
    }
    TRANSFERT {
        int id PK
        int exemplaire_id FK
        int site_origine_id FK
        int site_destination_id FK
        int reservation_id FK "nullable"
        enum motif "RESERVATION, REEQUILIBRAGE"
        datetime date_demande
        datetime date_reception "nullable"
    }
```

## 2. Les choix de conception

### 2.1 Œuvre → Édition → Exemplaire (inspiré de FRBR)
- `Oeuvre` : le contenu intellectuel (*1984*).
- `Edition` : une publication précise (ISBN, éditeur, langue, type de document).
- `Exemplaire` : l'objet physique avec un code-barres, c'est lui qui est prêté.
- Les statistiques (documents les plus empruntés) peuvent se faire par exemplaire, par édition ou par œuvre.

### 2.2 Personne + Contribution (auteurs multiples, traducteurs, illustrateurs)
- Une `Contribution` relie une `Personne` à **soit** une `Oeuvre`, **soit** une `Edition`, avec un `role`.
- Règle d'intégrité : **exactement un** des deux champs `oeuvre_id` / `edition_id` est renseigné (validation dans l'entité, contrainte `CHECK` possible en MySQL 8).
- Auteur, scénariste, dessinateur, réalisateur : au niveau de l'**œuvre**. Traducteur, préfacier : au niveau de l'**édition**.

### 2.3 Réservation : œuvre obligatoire, édition optionnelle
- Si `edition_id` est **vide**, l'adhérent accepte le premier exemplaire disponible de n'importe quelle édition de l'œuvre.
- Si `edition_id` est **renseigné**, seuls les exemplaires de cette édition conviennent.
- La file d'attente est ordonnée par `date_creation` : le premier réservant est servi au retour du document.

### 2.4 Deux sites sur l'exemplaire + Transfert
- `site_attache` : où l'exemplaire est rattaché. `site_actuel` : où il se trouve physiquement.
- Un `Transfert` est créé quand un exemplaire doit changer de site (réservation sur l'autre site, rééquilibrage). Pendant le trajet, l'exemplaire est `EN_TRANSIT`.

### 2.5 Cycle de vie de la réservation

```
EN_ATTENTE → DISPONIBLE → RETIREE
     │            │
     └→ ANNULEE   └→ EXPIREE (7 jours ouvrés dépassés)
```

- Au retour d'un exemplaire (ou à l'arrivée d'un transfert), le premier de la file passe à `DISPONIBLE` et `date_expiration_retrait = maintenant + 7 jours ouvrés`.
- À l'expiration : la réservation passe à `EXPIREE`, le suivant dans la file est servi, sinon l'exemplaire redevient `DISPONIBLE`.

### 2.6 Montants en centimes (entiers)
Pas de décimaux pour l'argent : les flottants provoquent des erreurs d'arrondi (0,1 + 0,2 ≠ 0,3). Monnaie : MAD.

### 2.7 Utilisateur séparé d'Adhérent
Le personnel (bibliothécaire, administrateur) a un compte `Utilisateur` mais pas de fiche `Adherent`. `utilisateur_id` est donc nullable sur l'adhérent. Rôles : `ROLE_ADHERENT`, `ROLE_BIBLIOTHECAIRE`, `ROLE_ADMIN`.

### 2.8 Concurrence (deux opérations en même temps sur un exemplaire)
- **Verrou pessimiste** (`SELECT ... FOR UPDATE`) sur l'exemplaire dans une transaction pour tout prêt, retour ou mise de côté.
- **Verrou optimiste** : la colonne `version` de l'exemplaire détecte les modifications concurrentes.
- Un exemplaire ne peut avoir qu'un seul prêt `EN_COURS` à la fois : vérifié dans `PretService` sous verrou, et testé automatiquement.

## 3. Règles métier portées par le modèle

| Règle | Où elle vit |
|---|---|
| Nombre de prêts simultanés et durée | `FormuleAbonnement.prets_max` / `duree_pret_jours` |
| Prolongation seulement si personne n'attend | `PretService`, en regardant les `Reservation` `EN_ATTENTE` de l'œuvre |
| Nombre maximal de prolongations | `Pret.nombre_prolongations` ≤ `FormuleAbonnement.prolongations_max` |
| Pénalité de retard | `Penalite` (type `RETARD`) = jours de retard × `penalite_centimes_par_jour` |
| Exemplaire perdu ou abîmé | `Exemplaire.statut` + `Penalite` (type `PERDU` ou `ABIME`) |
| Adhérent bloqué si abonnement expiré ou pénalités impayées | `PretService` |

## 4. Limites connues (v1)

- L'envoi d'e-mail de notification dépend de la configuration de Symfony Mailer (voir `DECISIONS.md`) ; à défaut, notification dans l'espace adhérent.
- Pas de gestion des jours fériés pour le calcul des « jours ouvrés » : on exclut seulement samedi et dimanche.
- Pas de paiement en ligne des pénalités : le bibliothécaire enregistre le paiement.