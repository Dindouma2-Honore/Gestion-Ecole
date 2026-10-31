# Architecture du module Finances

## Objectif

Gérer les flux financiers de l’établissement : frais et grilles tarifaires, paiements et répartitions, factures de préinscription, caisse, budgets, dépenses, remises et recouvrement.

## Organisation du code

- `Models/` : paiements, factures, lignes et tranches de frais, sessions et mouvements de caisse, budgets, dépenses, relances, promesses de paiement, échéanciers et exonérations.
- `Services/` : `FraisScolaireService`, `PaiementService`, `FacturePreinscriptionService`, `CaisseService`, `RecouvrementService`, `BudgetService`, services de dépense, bilan et répartition.
- `Contracts/` : interfaces publiques pour les paiements, frais, caisse, budget, dépenses, facturation, recouvrement et bilans. Certains contrats sont aussi des ports implémentés pour Scolarité.
- `Filament/Resources/` : gestion des paiements, frais, grilles, factures, budgets, dépenses, caisse, remises, échéanciers et recouvrements. Pages et widgets présentent situation, caisse et consommation budgétaire.
- `Console/` : commande de relance du recouvrement; aucun dossier `Jobs/` n’est présent actuellement.
- `Providers/FinancesServiceProvider.php` : bindings des contrats, migrations/vues, policies et commandes du module.

## Communications métier

Finances consomme des contrats Scolarité pour classes, élèves, inscriptions et bilans; son service de facture de préinscription est lié au port de facturation de Scolarité. Il consomme aussi les services du Socle pour année scolaire, audit, documents, paramétrage, workflow et périmètre par niveau. Les appels vers Communication ou Scolarité doivent respecter les interfaces déclarées; quelques exceptions historiques sont listées dans `deptrac.yaml`.

Les écritures financières sensibles passent par les services métier qui orchestrent transactions, numérotation, audit et statuts. Une Resource Filament ne doit pas reproduire ces règles en écrivant directement plusieurs modèles.
