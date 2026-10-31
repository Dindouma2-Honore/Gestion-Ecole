<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Table;

final class AmbassadorsDesign
{
    /** Couleur sémantique unique pour toute décision métier de validation. */
    public const VALIDATION_COLOR = 'warning';

    /** Palette neutre des cartes KPI du tableau de bord Administration. */
    public const ADMINISTRATION_KPI_COLOR = 'gray';

    public const NAVIGATION_GROUPS = [
        'Utilisateurs & accès',
        'Établissement & paramètres',
        'Années & périodes',
        'Audit & sécurité',
        'Configuration système',
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

    /** Proportions au Nombre d'Or (rapport ~1.618) pour les modales du socle visuel ERP. */
    public const GOLDEN_RATIO = 1.618;

    public const GOLDEN_FORM_WIDTH = 'min(906px, calc(100vw - 48px))';

    public const GOLDEN_FORM_MIN_HEIGHT = 'min(560px, calc(100dvh - 48px))';

    public const GOLDEN_DIALOG_WIDTH = 'min(720px, calc(100vw - 48px))';

    public const GOLDEN_DIALOG_MIN_HEIGHT = 'min(445px, calc(100dvh - 48px))';

    public const GOLDEN_RADIUS = '18px';

    /**
     * Retourne les attributs HTML d'une fenêtre modale respectant le nombre d'or.
     *
     * @param  'form'|'dialog'  $variant
     * @return array{class: string, style: string}
     */
    public static function goldenModalAttributes(string $variant = 'form'): array
    {
        if ($variant === 'dialog') {
            return [
                'class' => 'amb-modal-golden-md',
                'style' => 'width: '.self::GOLDEN_DIALOG_WIDTH.'; min-height: '.self::GOLDEN_DIALOG_MIN_HEIGHT.'; max-width: calc(100vw - 48px); max-height: calc(100dvh - 48px); border-radius: '.self::GOLDEN_RADIUS.'; margin: auto;',
            ];
        }

        return [
            'class' => 'amb-modal-golden-form',
            'style' => 'width: '.self::GOLDEN_FORM_WIDTH.'; min-height: '.self::GOLDEN_FORM_MIN_HEIGHT.'; max-width: calc(100vw - 48px); max-height: calc(100dvh - 48px); border-radius: '.self::GOLDEN_RADIUS.'; margin: auto;',
        ];
    }

    /**
     * Configure une action Filament pour utiliser le socle visuel de modale au nombre d'or.
     *
     * @template TAction of \Filament\Actions\Action
     *
     * @param  TAction  $action
     * @param  'form'|'dialog'  $variant
     * @return TAction
     */
    public static function configureGoldenModal(Action $action, string $variant = 'form'): Action
    {
        return $action
            ->modal(true)
            ->modalWidth('none')
            ->extraModalWindowAttributes(self::goldenModalAttributes($variant));
    }

    /**
     * Modale de formulaire au nombre d'or (906 x 560 px).
     * Recommandée pour les formulaires sur 2 colonnes avec aération maximale.
     *
     * @template TAction of \Filament\Actions\Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    public static function goldenFormModal(Action $action): Action
    {
        return self::configureGoldenModal($action, 'form');
    }

    /**
     * Modale de dialogue / consultation / confirmation au nombre d'or (720 x 445 px).
     *
     * @template TAction of \Filament\Actions\Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    public static function goldenDialogModal(Action $action): Action
    {
        return self::configureGoldenModal($action, 'dialog');
    }

    /**
     * Configure un tableau Filament selon le socle visuel ERP Ambassadors.
     * Réutilisable dans tous les modules (RH, Scolarité, Pédagogie, Finances, Socle).
     *
     * @param  string|null  $recordAction  Action déclenchée au clic sur la ligne (ex: 'edit')
     */
    public static function configureTable(Table $table, ?string $recordAction = 'edit'): Table
    {
        $table
            ->striped()
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100])
            ->recordUrl(null);

        if ($recordAction !== null) {
            $table->recordAction($recordAction);
        }

        return $table;
    }

    /**
     * Groupe d'actions standardisé pour les lignes de tableau avec le style ERP.
     *
     * @param  array<Action|ActionGroup>  $actions
     */
    public static function tableActionGroup(array $actions): ActionGroup
    {
        return ActionGroup::make($actions)
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('gray')
            ->tooltip('Actions');
    }

    private function __construct() {}
}
