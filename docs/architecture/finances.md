# Finances

## Objectif

Finances gère les budgets, dépenses, caisses, paiements, recouvrement et frais, en distinguant les flux scolaires et les flux généraux.

## Code

- **Modèles** : `Budget`, `BudgetCategorie`, `BudgetLigne`, `Depense`, `RubriqueDepense`, `SessionCaisse`, `MouvementCaisse`, `Paiement`, `PaiementAnnulation`, `RelancePaiement`, `FacturePreinscription`, `GrilleFrais`, `ConfigurationFraisClasse`, `RemiseExoneration`, échéanciers et groupes de frais.
- **Services** : budget, dépenses, caisse, paiements, répartition, frais scolaires, factures, recouvrement, balances et bilan financier par classe.
- **Contrats** : budgets, caisse, paiements, dépenses, frais, recouvrement, balances et facturation de préinscription.
- **Filament** : budget, dépenses, caisse, paiements, frais, factures, relances, remises et mouvements.

## Communications

Scolarité fournit l’inscription et le contexte de l’élève au travers de Contracts. Communication peut transmettre reçus ou relances. Le Socle fournit autorisations, audit, numérotation et année scolaire.

## Règle métier

Les paiements et clôtures de caisse sont des opérations transactionnelles et auditées. Les éléments scolaires sont initiés depuis Scolarité mais restent comptabilisés par Finances.
