# Architecture du module Socle

## Objectif

Fournir les services transverses de l’application : comptes/utilisateurs et habilitations, configuration de l’établissement, cycles/niveaux/filières, années et périodes, audit, documents, numérotation, workflows, courriers, tâches, réunions et notifications.

## Organisation du code

- `Models/` : utilisateurs et journaux, année scolaire/périodes/référentiels, configuration, documents et versions, templates, workflows, tâches, courriers, réunions, habilitations et groupes.
- `Contracts/` : surface publique du Socle : services d’année, audit, documents, workflows, habilitations, utilisateurs, configuration, numérotation, tâches, réunions et notifications, ainsi que traits/scopes contractuels.
- `Services/` : une implémentation par service transverse; certains transferts optionnels peuvent avoir une implémentation indisponible explicite.
- `Policies/`, `Traits/`, `Observers/`, `Listeners/`, `Notifications/` : autorisation, verrouillage, audit et réactions aux événements d’authentification.
- `Filament/Pages/` et `Resources/` : administration des paramètres, années, permissions, documents, workflows, groupes, réunions, tâches, audit et référentiels.
- `Http/Controllers/` : téléchargement signé de documents et périodes scolaires.
- `Console/Commands/` : alertes de documents expirants et relances de tâches.
- `SocleServiceProvider` lie les contrats, enregistre policies/événements/observer/middleware, migrations, vues et commandes.
- Aucun dossier `Jobs/` n’est présent; les commandes planifiées restent des commandes Console Laravel.

## Communications

Les autres modules consomment les contrats du Socle pour les opérations transverses plutôt que dupliquer audit, numérotation, année ou documents. Le Socle appelle les contrats de Communication pour des notifications et de Scolarité pour des destinataires de courriers. Les événements Login, Failed et Logout enregistrent l’activité et actualisent la connexion; le middleware vérifie l’état utilisateur. Les policies et habilitations conditionnent l’accès Filament et HTTP.
