# Scolarité

## Objectif

Scolarité est propriétaire du parcours administratif de l’élève : familles, élèves, classes, inscriptions, transferts, documents et frais scolaires de référence.

## Code

- **Modèles** : `Eleve`, `ParentTuteur`, `Classe`, `Inscription`, `DocumentEleve`, `TypeDocumentEleve`, `TransfertAffectation`, `Frais`, `CategorieFrais`, `GrilleTarifaire`, `FactureGeneree` et paiements historiques.
- **Services** : `EleveService`, `ParentTuteurService`, `ClasseService`, `InscriptionService`, `InscriptionParcoursService`, `DossierEleveService`, `TransfertAnneeService`, `FraisService` et `TableauHonneurService`.
- **Contrats** : classes, élèves, parents, inscriptions, facturation, destinataires de courrier et lecture pédagogique des classes.
- **Filament** : élèves, parents/tuteurs, classes, inscriptions, documents, transferts, frais et grilles tarifaires.

## Communications

Pédagogie obtient les classes par `ClassesPedagogiquesReadContract` et ne duplique ni élèves ni inscriptions. Finances facture les inscriptions via `InscriptionFacturationPort`. Communication reçoit les événements d’inscription et les destinataires via Contracts. Vie scolaire et Assiduité consomment la liste des élèves par classe.

## Règle métier

L’inscription reste administrative ; Pédagogie peut produire une décision pédagogique, mais ne crée pas l’inscription de l’année suivante.
