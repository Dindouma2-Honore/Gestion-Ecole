# Audit et mise à jour des habilitations

Vérification du 14 septembre 2026. Catalogue appliqué à la base locale : 78 entrées, couvrant les 10 modules de navigation et une entrée transversale historique Rapports. Les correspondances explicites couvrent 154 ressources et pages ; les tableaux de bord non spécialisés restent contrôlés par leur module.

## Corrections

- Catalogue central partagé par le seeder et la résolution des écrans : `app/Filament/Support/habilitations-catalogue.json` et `HabilitationCatalog`.
- Ajout des fonctions absentes : templates WhatsApp, bulletins/conseils, emplois du temps, enseignements, contrats, recrutement, formation, discipline RH, facturation scolaire, recouvrement, rapports financiers, documents, workflows et maintenance.
- Correspondance par classe et module avant les anciennes règles génériques. Cela corrige notamment `SortieEleveResource` auparavant reconnu comme fiche élève, `ConseilClasseResource` comme classe scolaire, et les référentiels Niveau du Socle rattachés à Scolarité.
- Matrice de rôle alignée sur le contrôle effectif : un rôle personnalisé sans attribution n’est plus affiché comme autorisé par défaut.
- Synchroniser le catalogue ne crée plus automatiquement des attributions actives pour tous les rôles. Les attributions explicites et désactivations de fonctionnalités sont conservées.
- Module Technique ajouté dans la base locale. Ses actions conservent leurs contrôles dédiés `technique.*` ; l’entrée du catalogue ne remplace pas ces permissions.

## Validation

Migration ciblée appliquée : `2026_09_14_120000_synchroniser_catalogue_habilitations`.
Comparaison avant/après : mêmes empreintes des trois tables d’attributions ; 294 attributions de rôles, aucune attribution spécifique utilisateur ou groupe dans cette base. Aucun rôle nouveau ni droit Spatie attribué pendant l’intervention.

Les pages Livewire ManageHabilitations, ManageUserPermissions et ManageGroupePermissions passent le contrôle de rendu sur la base locale, avec le profil administrateur existant. **16 tests réussis, 287 assertions** sous PHP 8.3. Les tests ciblés couvrent tous les fichiers Resource, les collisions de noms, la conservation des désactivations, l’absence d’attribution à la synchronisation et le refus d’accès direct à un template malgré l’accès au module.

## Portée des droits

Les habilitations dynamiques contrôlent l’accès aux modules et familles d’écrans. Les permissions d’action (créer, valider, publier, soumettre à Meta, etc.) restent vérifiées par les policies, services et contrôles propres aux modules. Activer « Templates WhatsApp » ne donne donc pas automatiquement le droit de soumettre ou supprimer un modèle chez Meta. Ces droits fins restent gérés dans Rôles et permissions.

La règle historique de défaut pour les cinq rôles staff est conservée ; les rôles personnalisés nécessitent une attribution. L’accès particulier à l’inscription pour le personnel et les contraintes sur les factures de préinscription sont conservés. Les groupes restent additifs sous réserve de la priorité des décisions utilisateur. Aucun audit exhaustif de chaque action métier ni test avec chaque profil réel n’est revendiqué ; les contrôles réalisés portent sur le catalogue, sa correspondance aux écrans et les régressions d’accès ciblées.

## Catalogue après mise à jour

### Socle

| Code | Fonctionnalité |
| --- | --- |
| `module.socle` | Administration générale |
| `socle.utilisateurs` | Gestion des utilisateurs |
| `socle.roles` | Gestion des rôles & permissions |
| `socle.audit` | Journal d'audit & sécurité |
| `socle.parametres` | Paramétrage général |
| `socle.documents` | Documents et modèles |
| `socle.courriers` | Courriers et modèles de courrier |
| `socle.organisation` | Réunions et tâches |
| `socle.workflows` | Circuits de validation |

### RH

| Code | Fonctionnalité |
| --- | --- |
| `module.rh` | Ressources humaines |
| `rh.employes` | Gestion du personnel |
| `rh.enseignants` | Gestion des enseignants |
| `rh.conges` | Gestion des congés |
| `rh.paie` | Gestion de la paie |
| `rh.contrats` | Contrats du personnel |
| `rh.recrutement` | Recrutement et candidatures |
| `rh.formation` | Formations du personnel |
| `rh.discipline` | Discipline du personnel |
| `rh.pointage` | Pointages du personnel |

### Scolarite

| Code | Fonctionnalité |
| --- | --- |
| `module.scolarite` | Scolarité et inscriptions |
| `scolarite.eleves` | Fiches élèves & dossier |
| `scolarite.inscriptions` | Inscriptions & réinscriptions |
| `scolarite.classes` | Niveaux & classes |
| `scolarite.facturation` | Factures et tarifs scolaires |
| `scolarite.paiements` | Paiements et bilan journalier |

### Pedagogie

| Code | Fonctionnalité |
| --- | --- |
| `module.pedagogie` | Pédagogie |
| `pedagogie.programmes` | Programmes & matières |
| `pedagogie.seances` | Séances & cahier de texte |
| `pedagogie.evaluations` | Notes & évaluations |
| `pedagogie.discipline` | Discipline des élèves |
| `pedagogie.bulletins` | Bulletins et conseils de classe |
| `pedagogie.emplois_du_temps` | Emplois du temps et créneaux |
| `pedagogie.enseignements` | Enseignements et affectations |

### Finances

| Code | Fonctionnalité |
| --- | --- |
| `module.finances` | Finances |
| `finances.factures` | Factures & préinscriptions |
| `finances.paiements` | Paiements & règlements |
| `finances.caisse` | Gestion de caisse |
| `finances.budget` | Budget & seuils |
| `finances.depenses` | Dépenses et rubriques |
| `finances.recouvrement` | Recouvrement et relances |
| `finances.rapports` | Situation financière et balance |
| `finances.grilles_salariales` | Grilles salariales |

### Communication

| Code | Fonctionnalité |
| --- | --- |
| `module.communication` | Communication |
| `communication.annonces` | Annonces & communications |
| `communication.rendezvous` | Prise de rendez-vous |
| `communication.notifications` | Notifications & SMS |
| `communication.templates` | Templates WhatsApp |

### Assiduite

| Code | Fonctionnalité |
| --- | --- |
| `module.assiduite` | Assiduité |
| `assiduite.pointage` | Pointage & présences |
| `assiduite.reclamations` | Réclamations & incidents |
| `assiduite.dashboard` | Dashboard |
| `assiduite.eleves` | Eleves |
| `assiduite.absences` | Absences |
| `assiduite.personnel` | Personnel |
| `assiduite.biometrie` | Biometrie |
| `assiduite.justifications` | Justifications |
| `assiduite.anomalies` | Anomalies |
| `assiduite.rapports` | Rapports |

### VieScolaire

| Code | Fonctionnalité |
| --- | --- |
| `viescolaire.visiteurs` | Visiteurs |
| `module.viescolaire` | Vie scolaire |
| `viescolaire.sante` | Santé & infirmerie |
| `viescolaire.sorties` | Sorties des élèves |
| `viescolaire.infirmerie` | Infirmerie |
| `viescolaire.incidents` | Incidents |
| `viescolaire.reclamations` | Reclamations |
| `viescolaire.calendrier` | Calendrier |
| `viescolaire.evenements` | Evenements |
| `viescolaire.rapports` | Rapports |
| `viescolaire.sante_modifier` | Santé — modification |

### Logistique

| Code | Fonctionnalité |
| --- | --- |
| `module.logistique` | Logistique |
| `logistique.salles` | Salles & équipements |
| `logistique.evenements` | Événements & réservations |
| `logistique.transport` | Transport scolaire |
| `logistique.cantine` | Cantine |
| `logistique.bibliotheque` | Bibliothèque |
| `logistique.equipements` | Équipements et maintenance |

### Transversal

| Code | Fonctionnalité |
| --- | --- |
| `module.rapports` | Rapports |

### Technique

| Code | Fonctionnalité |
| --- | --- |
| `module.technique` | Technique |

