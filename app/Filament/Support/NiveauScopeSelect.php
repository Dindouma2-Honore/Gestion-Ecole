<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Filament\Forms\Components\Select;

/**
 * Sélecteur partagé pour les paramètres pouvant viser un niveau ou tous.
 *
 * Appliqué aux jours fériés, réunions, salles et livres. Ne pas l'utiliser
 * pour les classes, programmes ou grilles de frais, dont le niveau est une
 * donnée métier obligatoire et non un simple périmètre d'application.
 */
final class NiveauScopeSelect
{
    public static function make(string $name = 'niveau_id'): Select
    {
        return Select::make($name)
            ->label('Niveau concerné')
            ->options(fn (): array => collect(app(ParametrageServiceContract::class)->getTousLesNiveaux())
                ->pluck('nom', 'id')
                ->all())
            ->placeholder('Tous les niveaux')
            ->helperText('Laissez « Tous les niveaux » pour appliquer ce paramètre aux trois niveaux.')
            ->searchable()
            ->preload()
            ->nullable();
    }

    private function __construct() {}
}
