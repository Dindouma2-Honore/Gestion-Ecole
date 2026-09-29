# Module Pédagogie — Développeur A (2/2)

Zone : Scolarité & pédagogie. Couvre les sous-modules du cahier des charges
liés à la pédagogie et à l'assiduité (Matières, Programmes, Emplois du
temps, Séances, Présences, Détection d'absences, Progression pédagogique,
Évaluations, Notes, Bulletins — les lots suivants : Relances, Examens,
Qualité pédagogique, Discipline des élèves restent à livrer, voir plus bas).

## Ce qui est fait

- **Matières** (`matieres`) et **Programmes** (`programmes`) — y compris la
  soumission par l'enseignant de son programme annuel en **PDF ou Word**
  (`ProgrammeServiceInterface::soumettreProgramme()`, action "Soumettre mon
  programme" dans `ProgrammeResource`), en attente de validation par le
  Directeur (`valider`/`rejeter`).
- **Emplois du temps** (`emplois_du_temps` + `creneaux_horaires`) — planning
  théorique, avec détection de conflit enseignant/salle/classe
- **Séances** (`seances`) — transforme le planning en séances réelles, avec
  machine à états (`Seance::TRANSITIONS_AUTORISEES`)
- **Présences / Appel** (`presences`) — appel par séance, justification
  d'absence, taux de présence
- **Détection d'absences** (`anomalies_appel`) — filet de sécurité si
  l'appel n'a pas été fait
- **Progression pédagogique** (`progressions`) — cahier de texte, dépend du
  statut "dispensée" de la séance. Le formulaire ne propose désormais que
  les chapitres du programme réellement prévus pour la matière/classe de la
  séance (via `ProgrammeServiceInterface::getChapitresPrevus()`), et une
  action "Déclarer un chapitre déjà couvert" permet à l'enseignant de
  renseigner les chapitres du programme qu'il a déjà couverts sans passer
  par une séance précise (rattrapage de progression, reprise en cours
  d'année) — `seance_id` est donc désormais nullable sur `progressions`.
- **Évaluations** (`evaluations`) — l'enseignant **propose un sujet
  d'examen** (`EvaluationServiceInterface::proposerSujet()`, avec fichier
  PDF/Word optionnel), qui doit être **validé par le Directeur**
  (`validerSujet()`/`rejeterSujet()`) avant que des notes ne puissent être
  saisies pour cette évaluation.
- **Notes** (`notes`) — verrouillage à J+7 après la première saisie (délai
  paramétrable via `ParametrageServiceContract`, déblocage exceptionnel
  réservé au Fondateur — logique déjà en place, inchangée), **plus** :
  - refus de saisie tant que le sujet de l'évaluation n'est pas validé, ou
    que la date de l'examen n'est pas passée ;
  - upload de la **copie de l'élève** (scan/photo), attachée via
    `DocumentServiceContract` ;
  - point d'ancrage (commenté, en attente du vrai contrat) pour la
    **notification WhatsApp au parent** — voir section dédiée plus bas.
- **Bulletins** (`bulletins` + `bulletin_matieres`) — génération du bulletin
  d'un élève ou de toute une classe (`BulletinServiceInterface`), moyenne
  par matière pondérée par le coefficient, moyenne générale, rang dans la
  classe, document PDF (ou HTML en repli) attaché via
  `DocumentServiceContract`.

## Contracts exposés

| Interface | Rôle |
|---|---|
| `MatiereServiceInterface` | existe(), getMatiere(), toutesActives(), creer() |
| `ProgrammeServiceInterface` | getProgrammesParNiveau(), creerProgramme(), soumettreProgramme(), validerProgramme(), rejeterProgramme(), getChapitresPrevus() |
| `ProgressionServiceInterface` | saisirProgression(), declarerChapitreCouvert(), getPourcentageAvancement(), getRetardPedagogique() |
| `EmploiDuTempsServiceInterface` | planifierCours(), modifierCours(), detecterConflits(), getCoursDeLaSemaine(), getEmploiDuTempsEnseignant() |
| `SeanceServiceInterface` | genererSeancesPourSemaine(), marquerCommencee(), marquerDispensee(), annuler(), reporter(), getStatut(), ... |
| `PresenceServiceInterface` | faireAppel(), appelDejaFait(), justifierAbsence(), getStatutJour(), enregistrerPointage(), ... |
| `DetectionAbsenceServiceInterface` | detecterAnomalies(), marquerResolue(), getAnomaliesNonResolues() |
| `EvaluationServiceInterface` | proposerSujet(), validerSujet(), rejeterSujet() |
| `NoteServiceInterface` | enregistrer() (avec upload de copie), peutModifier(), dateVerrouillage(), debloquer() |
| `BulletinServiceInterface` | genererPourEleve(), genererPourClasse() |

## Notification WhatsApp au parent (à faire par un autre développeur)

`NoteService::notifierParent()` est l'unique point d'ancrage prévu pour
déclencher l'envoi. Il est volontairement laissé en **no-op documenté**
(même convention que `DetectionAbsenceService`) tant que le vrai contrat de
notification (Socle ou futur module Communication) n'est pas confirmé :
pas de nom de méthode, de canal ou de gabarit imposés côté Pédagogie pour
ne pas bloquer l'intégration réelle. Il suffit de décommenter et de
compléter l'appel dans `NoteService::enregistrer()` une fois le contrat
disponible.

## Génération PDF des bulletins

`BulletinService::genererDocument()` utilise `barryvdh/laravel-dompdf`
**s'il est installé** (`class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)`).
Sinon, le bulletin est généré en HTML et attaché tel quel via
`DocumentServiceContract` — dégradation silencieuse, aucune fonctionnalité
ne casse en son absence, mais le rendu n'est alors pas un vrai PDF tant que
la dépendance n'est pas ajoutée au `composer.json` de l'application hôte.

## Dépendances

- Consomme `Scolarite\Contracts\ClasseServiceInterface` et
  `RH\Contracts\EnseignantServiceInterface` (emplois du temps).
- Consomme `Socle\Contracts\ParametrageServiceContract`,
  `Socle\Contracts\AuditServiceContract` et `Socle\Contracts\DocumentServiceContract`.
- `matiere_id`, `seance_id`, etc. référencés à l'intérieur du module :
  vraies contraintes de clé étrangère. Tout ID venant d'un **autre** module
  (`classe_id`, `enseignant_id`, `salle_id`, `annee_scolaire_id`, `eleve_id`,
  `saisi_par`, `document_justificatif_id`) reste une simple colonne, jamais
  de FK inter-module (voir `deptrac.yaml`).

⚠️ **TODO bloquant** : le module `RH` n'existe pas encore dans le dépôt
(aucun fichier sous `app/Modules/RH/`), alors que `EmploiDuTempsService` et
`SeanceService` dépendent de `RH\Contracts\EnseignantServiceInterface`.
Volontairement laissé tel quel (pas de stub) — tant que Dev B n'a pas
fusionné son Contract, `EmploiDuTempsServiceInterface` et
`SeanceServiceInterface` ne peuvent pas être résolus par le conteneur
(`app(...)` échouera). Les autres Contracts du module (Matière, Programme,
Progression) ne sont pas concernés et fonctionnent de manière indépendante.

## Interfaces Filament

- `MatiereResource`, `ProgrammeResource` (CRUD classique via Service)
- `EmploiDuTempsResource` (CRUD via Service, détection de conflit gérée par
  `EmploiDuTempsServiceInterface`)
- `SeanceResource` (liste seule — pas de création manuelle, les séances sont
  générées par le Job planifié — actions "Commencer" / "Annuler" / "Reporter"
  qui passent toutes par `SeanceServiceInterface`)
- `AnomalieAppelResource` (liste + action "Marquer résolue")
- **Reste à faire** : une page dédiée "Faire l'appel" (saisie groupée par
  classe/séance plutôt qu'un formulaire Filament générique) — actuellement
  seul `PresenceServiceInterface::faireAppel()` existe côté Service.

## Historique des corrections (violations résolues)

Ce lot corrige plusieurs violations laissées par une livraison précédente :

1. `MatiereServiceInterface`, `ProgrammeServiceInterface`,
   `EmploiDuTempsServiceInterface`, `SeanceServiceInterface`,
   `PresenceServiceInterface`, `ProgressionServiceInterface` étaient
   utilisées par les Services mais **n'existaient dans aucun fichier**
   (erreur fatale "classe introuvable").
2. Plusieurs Exceptions (`CodeMatiereDejaUtiliseException`,
   `MatiereIntrouvableException`, `SeanceNonDispenseeException`,
   `ConflitEmploiDuTempsException`, `SeanceIntrouvableException`,
   `TransitionStatutSeanceInvalideException`) étaient référencées mais
   jamais créées.
3. `SeanceService` importait `Socle\Contracts\AuditServiceInterface`, qui
   n'existe pas — corrigé en `Socle\Contracts\AuditServiceContract`.
4. Les migrations `emplois_du_temps`, `presences` et `progressions`
   posaient des **contraintes de clé étrangère inter-module en dur**
   (`classes`, `enseignants`, `salles`, `documents`, `utilisateurs`) —
   interdit par la règle d'or du projet — et référençaient même des tables
   inexistantes (`remplacements`, `programme_chapitres`) ou mal nommées
   (`utilisateurs` au lieu de `users`). Toutes corrigées en colonnes simples
   indexées pour les FK inter-module, en vraies FK pour les FK intra-module.
5. Un dossier fantôme `app/Modules/Pedagogie/{Contracts,Services,Models,...`
   (erreur de `mkdir -p` mal accolée) a été supprimé.
6. Interfaces Filament manquantes pour Emplois du temps / Séances /
   Anomalies d'appel — ajoutées.

## Corrections de ce lot (ajustements demandés)

1. **Bug de placement corrigé** : l'action "Soumettre mon programme"
   (upload PDF/Word) était câblée par erreur dans `ProgressionResource`
   (cahier de texte) au lieu de `ProgrammeResource` — déplacée à sa place.
2. Ajout du workflow **sujet d'examen soumis par l'enseignant / validé par
   le Directeur** sur `Evaluation` (statut `brouillon|soumis|valide|rejete`,
   fichier du sujet, motif de rejet).
3. `NoteService::enregistrer()` refuse désormais la saisie tant que le
   sujet n'est pas validé ou que l'examen n'a pas eu lieu.
4. Ajout de l'upload de la **copie de l'élève** sur `Note`.
5. Point d'ancrage pour la **notification WhatsApp parent** (implémentation
   réelle laissée à un autre développeur — voir section dédiée).
6. Nouveau sous-module **Bulletins** (par élève ou par classe entière).
7. **Progression pédagogique** : le choix du chapitre est désormais filtré
   sur le programme réellement prévu pour la matière/classe de la séance,
   et une action dédiée permet de déclarer un chapitre déjà couvert hors
   séance.

## Prochains lots (pas encore commencés)

Relances pédagogiques, Examens, Qualité pédagogique, Discipline des élèves
— à faire un par un, comme convenu, pas tout d'un coup dans une seule PR.
