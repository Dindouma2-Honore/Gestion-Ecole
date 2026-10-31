# Administration

## Objectif

L’administration est l’espace de gouvernance transversal de l’ERP. Elle ne possède pas un module métier séparé : elle assemble les capacités du Socle.

## Fonctionnalités

- Tableau de bord administratif.
- Liste des utilisateurs, création depuis le personnel RH, consultation, modification, suspension et suppression contrôlée.
- Rôles, permissions et matrice d’habilitations par fonctionnalité.
- Paramètres d’établissement, organisation, année scolaire, périodes et calendrier.
- Audit des actions et consultation des documents de référence.

## Code et communication

Les Resources et Pages Filament se trouvent sous `app/Filament` et `app/Modules/Socle/Filament`. Elles utilisent `UtilisateurService`, `AssignationRoleService`, `HabilitationService`, `AuditService` et `ParametrageService`.

L’administration lit le personnel via RH pour créer un compte, sans dupliquer sa fiche. Les autres modules demandent leurs autorisations au Socle ; ils ne modifient pas eux-mêmes les rôles ou les habilitations.
