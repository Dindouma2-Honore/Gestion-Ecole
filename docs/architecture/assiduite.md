# Assiduité

## Objectif

Assiduité traite les présences quotidiennes, justifications, sorties d’élèves, accueil des visiteurs, santé et réclamations d’assiduité.

## Code

- **Modèles** : `AssiduiteJournaliere`, `PointageBrut`, `AnomalieAssiduite`, `JustificationAssiduite`, `SortieEleve`, `Visiteur`, `DossierSante`, `VisiteInfirmerie`, `Reclamation` et historiques.
- **Services et contrats** : moteur de présence, justifications, santé, sorties, visiteurs et réclamations.
- **Filament** : pointage, journal d’assiduité, anomalies, justifications, sorties, santé, visiteurs et réclamations.

## Communications

Le module utilise les élèves et classes exposés par Scolarité. Il partage ses constats avec Vie scolaire selon les règles métier, sans prendre la propriété de l’inscription de l’élève. Le Socle fournit l’audit, l’année et les autorisations.
