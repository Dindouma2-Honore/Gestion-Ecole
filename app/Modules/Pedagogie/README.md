# Module Pédagogie

## Rôle

Le module Pédagogie rassemble les outils de préparation, de suivi et de bilan des enseignements. Il s'appuie sur les classes, les enseignants, les années et périodes scolaires gérés par les modules concernés. Il ne remplace pas la gestion des inscriptions élèves ni les référentiels de Scolarité et de Ressources humaines.

## Navigation

Le menu du module est défini dans `app/Filament/Support/ModuleNavigation.php` :

- **Tableau de bord** : indicateurs de séances, appels manquants, progression, évaluations, notes et bulletins. Le tableau de bord peut être filtré par cycle, classe, période et dates.
- **Organisation pédagogique** : point d'accès à Classes, Matières, Offres pédagogiques et Affectations.
- **Programmes & progression** : programmes et chapitres, progression de l'enseignant et supervision de la progression selon les habilitations.
- **Emploi du temps** : accès à la gestion des emplois du temps.
- **Séances & assiduité** : séances, appels et contenu enseigné.
- **Évaluations & notes** : évaluations, examens, validation des sujets et saisie des notes.
- **Résultats & bulletins** : bulletins, publication et décisions/conseils de classe.

Les éléments de menu ouvrent les Pages ou Resources Filament existantes. Les règles d'accès sont appliquées par les habilitations et autorisations de l'application.

## Organisation pédagogique

Cette partie décrit comment les données d'organisation se relient :

1. **Classes** présente les classes existantes, groupées par cycle puis par niveau et rattachées à une année scolaire. La page affiche, lorsqu'une classe est sélectionnée, son effectif, son cycle, son niveau, son enseignant principal, les matières issues des offres et leurs enseignants affectés, ainsi que l'emploi du temps actif. La page consulte les données existantes; elle ne crée pas un second référentiel de classes.
2. **Matières** gère le référentiel réutilisable des matières et domaines d'activité. Une matière est décrite par son nom, son type, sa catégorie, son code, ses coefficients éventuels, sa description et son statut actif. Les catégories sont gérées par la Resource de catégories de matières.
3. **Offres pédagogiques** associe une matière à une classe et une année scolaire, avec notamment son volume horaire hebdomadaire, son coefficient et son statut. C'est l'offre qui exprime qu'une matière est enseignée à une classe; les classes ne sont donc pas encodées dans le nom de la matière.
4. **Affectations** relie les enseignants aux classes, matières ou offres selon le type d'affectation. Les types existants comprennent principal, assistant et matière. Les données d'enseignants proviennent du référentiel du personnel.

Les modèles principaux de cette partie sont `Matiere`, `CategorieMatiere`, `OffrePedagogique` et `AffectationPedagogique`. Les Resources Filament associées portent le même nom. L'année scolaire est utilisée pour rattacher les classes, offres et affectations à leur contexte.

## Programmes et progression

Les programmes regroupent les contenus et chapitres prévus. Un programme peut être saisi ou soumis avec un document; le service de programme prend en charge son analyse et son workflow de validation. La progression enregistre le contenu effectivement couvert et les devoirs associés. Elle peut être reliée à une séance dispensée, et des déclarations de chapitres déjà couverts peuvent être faites hors d'une séance précise. Les vues enseignant et direction présentent respectivement l'avancement et les alertes de suivi; l'accès à la supervision dépend des habilitations.

## Emploi du temps, séances et assiduité

L'emploi du temps associe les classes, matières, enseignants et créneaux. Son service vérifie les conflits de planification. Les séances correspondent aux cours planifiés pour une date et suivent des transitions de statut contrôlées par le service de séances. L'appel est enregistré pour les séances et permet de suivre présences, absences et retards. Les anomalies d'appel signalent les appels manquants afin qu'ils soient traités.

## Évaluations et notes

Une évaluation décrit un devoir, contrôle ou examen pour une matière, une classe et une période. Le workflow permet la soumission puis la validation ou le rejet du sujet. La saisie des notes s'appuie sur le service des notes; des contrôles encadrent la saisie et les modifications, dont le verrouillage après le délai configuré. Une copie d'élève peut être jointe à une note lorsque le parcours de dépôt est utilisé.

## Résultats, bulletins et décisions

Le service des bulletins calcule les résultats à partir des notes et coefficients, puis permet de générer et gérer les bulletins selon leur état (préparation, validation, publication ou archivage). Les conseils de classe et décisions pédagogiques portent les décisions collectives ou de passage. La disponibilité des actions dépend des droits associés à l'utilisateur.

## Structure technique

- `Models/` : entités pédagogiques et leurs relations locales.
- `Services/` et `Contracts/` : règles métier exposées aux interfaces et aux autres composants autorisés.
- `Filament/Resources/` : listes et opérations de gestion des entités.
- `Filament/Pages/` : tableaux de bord, vues de synthèse et pages de section.
- `database/migrations/` : structure de données propre au module.
- `resources/views/` : vues Blade Filament et modèles de documents.

`PedagogieServiceProvider` enregistre les services métier, charge les migrations du module et rend ses vues disponibles sous l'espace de noms `pedagogie::`.

## Points à garder en tête

- Les menus et leurs destinations décrivent les fonctionnalités exposées; ils ne signifient pas que chaque action est accessible à chaque profil.
- Classes pédagogiques est une vue de consultation des classes, offres, affectations et emplois du temps existants.
- Les opérations métier doivent passer par les services du module quand un contrat de service existe, afin de conserver les validations et transitions prévues.
- Le README historique indiquait que certaines intégrations externes, notamment autour des enseignants et des notifications, dépendaient de contrats d'autres modules. Vérifier les bindings et les contrats de l'installation avant de considérer ces intégrations disponibles dans chaque environnement.
