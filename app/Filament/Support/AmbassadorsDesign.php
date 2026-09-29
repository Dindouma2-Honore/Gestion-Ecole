<?php

declare(strict_types=1);

namespace App\Filament\Support;

final class AmbassadorsDesign
{
    /** Couleur sémantique unique pour toute décision métier de validation. */
    public const VALIDATION_COLOR = 'warning';

    /** Palette neutre des cartes KPI du tableau de bord Administration. */
    public const ADMINISTRATION_KPI_COLOR = 'gray';

    public const NAVIGATION_GROUPS = [
        'Utilisateurs & accès',
        'Structure de l’établissement',
        'Années & périodes',
        'Modèles de documents',
        'Workflows & validations',
        'Audit & sécurité',
        'Scolarité',
        'Pédagogie',
        'Ressources humaines',
        'Finances',
        'Assiduité',
        'VieScolaire',
        'Logistique',
    ];

    /** @return array<string, string> */
    public static function statutLabels(): array
    {
        return [
            'brouillon' => 'Brouillon',
            'active' => 'Active',
            'actif' => 'Actif',
            'en_attente' => 'En attente',
            'cloturee' => 'Clôturée',
            'archivee' => 'Archivée',
            'suspendu' => 'Suspendu',
            'annule' => 'Annulé',
            'rejete' => 'Rejeté',
            'valide' => 'Validé',
        ];
    }

    public static function statutColor(?string $statut): string
    {
        return match ($statut) {
            'active', 'actif', 'valide' => 'success',
            'brouillon', 'en_attente' => 'warning',
            'cloturee' => 'info',
            'archivee' => 'gray',
            'suspendu', 'annule', 'rejete' => 'danger',
            default => 'primary',
        };
    }

    private function __construct() {}
}
