# Architecture du module Pédagogie

## Objectif

Préparer, organiser, suivre et évaluer l’enseignement. Le module consomme les classes et élèves de Scolarité, les enseignants RH, les années/périodes du Socle et certaines ressources d’infrastructure. Il ne possède pas le référentiel d’inscriptions ni celui du personnel.

## Domaines et modèles

- **Organisation pédagogique** : `Matiere`, `CategorieMatiere`, `OffrePedagogique` et `AffectationPedagogique` décrivent les matières, leur disponibilité par classe/année, leurs volumes et les enseignants affectés.
- **Programmes et progression** : `Programme`, `ProgrammeChapitre`, `ProgrammeLecon`, `ProgrammeAnalyse`, `Progression` et `Devoir` portent le contenu prévu, son analyse et son avancement réel.
- **Emploi du temps, séances et appels** : `EmploiDuTemps`, `CreneauHoraire`, `Seance`, `Presence` et `AnomalieAppel`.
- **Évaluations et résultats** : `Evaluation`, `Note`, `Bulletin`, ses modèles de lignes/versions, conseils et décisions.
- D’autres modèles couvrent discipline des élèves et suivi complémentaire.

## Services, interface et traitements différés

`Services/` concentre les opérations métier : emploi du temps, séances, présence, évaluations, notes, bulletins, programmes, progression, matières et affectations. Les `Contracts/` exposent les services du module et l’événement `ReportCardPublished`. Les `Filament/Resources/` sont les interfaces CRUD; les Pages fournissent tableau de bord, Classes pédagogiques, emplois du temps, programmes/progression, séances/assiduité, évaluations/résultats et bulletins/conseils.

`ProcessProgrammeDocumentJob` traite un document de programme; `AnalyzeProgrammeJob` extrait/analyse son contenu et enregistre le résultat. `ProgrammeService` les déclenche au cours du flux de dépôt/analyse; certains chemins d’analyse peuvent être synchrones (`dispatchSync`), d’autres utilisent la queue Laravel. Le contrôleur d’analyse et les vues Blade complètent ce parcours. `PedagogieServiceProvider` lie les contrats, charge migrations et vues `pedagogie::`.

## Communications et frontières

Pédagogie consomme les contrats de Scolarité (classe/élève), RH (enseignant/absence), Socle (année, audit, documents et paramétrage) et Logistique (infrastructure). Communication écoute l’événement de publication du bulletin. Les intégrations doivent préserver ces ports; les modèles d’autres modules ne sont pas des dépendances directes.

Les offres pédagogiques et affectations sont les sources de l’emploi du temps; les séances consomment les cours planifiés; appels, évaluations, notes et bulletins suivent leurs propres workflows. Les responsabilités détaillées par sous-menu restent dans le [README du module](README.md).
