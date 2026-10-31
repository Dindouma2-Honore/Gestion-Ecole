<?php

declare(strict_types=1);

namespace App\Filament\Support;

final class AdministrationHelp
{
    public static function forScopes(array $scopes): ?array
    {
        $guides = [
            'SocleDashboard' => ['Tableau de bord', 'Consultez les indicateurs de l’administration et utilisez les accès rapides pour ouvrir la gestion des comptes, des permissions ou des paramètres.'],
            'UserResource' => ['Utilisateurs', 'Ce menu liste les membres du personnel ayant un compte dans l’application. Recherchez une personne puis utilisez le menu Actions (⋮) pour consulter, modifier ou supprimer son compte. Pour créer un compte, cliquez sur Créer un utilisateur et choisissez un membre du personnel existant. Son invitation WhatsApp dépend de la disponibilité du service et de la validation du modèle Meta.'],
            'ManageHabilitations' => ['Habilitation du personnel', 'Définissez les accès aux modules et aux fonctionnalités par rôle du personnel. Sélectionnez le rôle concerné, ajustez ses habilitations, puis enregistrez vos modifications.'],
            'ManageUserPermissions' => ['Habilitations des utilisateurs', 'Sélectionnez un utilisateur pour consulter et ajuster ses accès individuels. Toute modification est consignée dans l’audit.'],
            'ManageGroupePermissions' => ['Habilitations des groupes', 'Sélectionnez un groupe et réglez les fonctionnalités auxquelles ses membres peuvent accéder.'],
            'RoleResource' => ['Rôles', 'Consultez les profils d’accès disponibles. Ouvrez un rôle pour modifier ses informations selon vos habilitations.'],
            'PermissionResource' => ['Permissions', 'Consultez les autorisations disponibles dans l’application. Utilisez la recherche pour retrouver une permission et les actions pour consulter ou modifier sa définition.'],
            'GroupeResource' => ['Groupes', 'Organisez les utilisateurs en groupes pour faciliter la gestion de leurs accès. Ouvrez un groupe pour consulter ou modifier ses informations.'],
            'AdministrationSettings' => ['Paramètres', 'Choisissez la rubrique à configurer : établissement, organisation, années scolaires ou calendrier. Chaque rubrique ouvre son propre écran.'],
            'ManageEtablissementSettings' => ['Établissement', 'Renseignez l’identité, le logo et les coordonnées de l’établissement, puis enregistrez. Les horaires et les jours ouvrables se règlent séparément dans Calendrier.'],
            'OrganisationSettings' => ['Organisation & Organigramme', 'Visualisez le schéma hiérarchique de l’établissement selon les fonctions RH et le personnel en poste. Vous pouvez ajuster les lignes hiérarchiques et affecter des collaborateurs.'],
            'AcademicYearSettings' => ['Années scolaires & périodes', 'Choisissez Années scolaires pour gérer les années de l’établissement, ou Périodes pour définir leurs périodes pédagogiques.'],
            'CalendarSettings' => ['Calendrier', 'Choisissez Jours ouvrables, Jours fériés ou Horaires généraux. Chaque écran permet de modifier uniquement le réglage sélectionné.'],
            'ManageWorkingDays' => ['Jours ouvrables', 'Cochez les jours de la semaine durant lesquels l’établissement fonctionne, puis enregistrez. Les heures d’ouverture se règlent dans Horaires généraux.'],
            'ManageGeneralHours' => ['Horaires généraux', 'Définissez les heures d’ouverture et de fermeture ainsi que la tolérance de retard, puis enregistrez. L’heure de fermeture doit être postérieure à l’ouverture.'],
            'AnneeScolaireResource' => ['Années scolaires', 'Créez une année avec ses dates, puis utilisez les actions proposées pour l’activer, la clôturer ou l’archiver selon son état.'],
            'PeriodeResource' => ['Périodes', 'Définissez les périodes rattachées aux années scolaires. Ouvrez une période pour vérifier ou modifier ses informations.'],
            'JourFerieResource' => ['Jours fériés', 'Ajoutez les dates de fermeture exceptionnelles et leur libellé. Utilisez les actions du tableau pour corriger ou supprimer une entrée.'],
            'ActivityLogResource' => ['Audit', 'Consultez l’historique des actions réalisées dans l’application. Utilisez la recherche et les filtres disponibles pour retrouver une opération et en vérifier les détails.'],
        ];

        foreach ($scopes as $scope) {
            if (! str_starts_with($scope, 'App\\Modules\\Socle\\')) {
                continue;
            }
            foreach (explode('\\', $scope) as $part) {
                if (isset($guides[$part])) {
                    return ['title' => $guides[$part][0], 'text' => $guides[$part][1]];
                }
            }
        }

        return null;
    }
}
