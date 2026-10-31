# Architecture du module Scolarité

## Objectif

Être propriétaire des dossiers élèves et familles, des classes, des inscriptions/réinscriptions et des frais scolaires de base, avec leurs documents et transferts d’année.

## Organisation du code

- `Models/` : `Eleve`, `ParentTuteur`, contacts d’urgence, `Classe`, `Inscription`, documents élève, frais, paiements de scolarité et tableaux d’honneur.
- `Services/` : élèves, parents/tuteurs, classes, inscriptions, frais, factures, dossiers, transferts d’année, courriers destinataires et tableaux d’honneur.
- `Contracts/` : ports publics pour élèves, parents, classes, inscriptions, factures, frais et destinataires d’événements.
- `Filament/Resources/` : élèves, familles, classes, inscriptions, documents, frais, paiements, transferts et factures; Pages et Widgets pour le tableau de bord et bilans.
- `Http/Controllers/` : impression de facture et d’inscriptions.
- `ScolariteServiceProvider.php` lie les contrats, charge migrations/vues et attache les observers de verrouillage annuel aux modèles concernés.
- Aucun dossier `Jobs/` n’est présent actuellement.

## Flux et frontières

Scolarité est l’unique propriétaire de ses classes, élèves, familles et inscriptions. Pédagogie consulte classes/élèves via les contrats de Scolarité. Finances fournit le port de facturation des préinscriptions et les bilans financiers utilisés par Scolarité; le Socle fournit années, audit, documents, paramétrage, scopes et transfert d’année. Des événements d’inscription peuvent être consommés par Communication.

Les créations/validations d’inscription, matricules, capacités, frais figés et états de paiement sont orchestrés par les services. Les formulaires Filament présentent le parcours mais ne doivent pas dupliquer ces règles. Les schémas du module résident sous `database/migrations/`.
