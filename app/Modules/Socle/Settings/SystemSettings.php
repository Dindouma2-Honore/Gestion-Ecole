<?php

declare(strict_types=1);

namespace App\Modules\Socle\Settings;

use Spatie\LaravelSettings\Settings;

class SystemSettings extends Settings
{
    public int $delaiToleranceMinutes;

    public int $delaiPremiereRelanceHeures;

    public int $delaiDeuxiemeRelanceHeures;

    public int $delaiModificationNotesJours;

    public float $tauxCotisationCnpsSalarie;

    public int $joursOuvrablesPaie;

    public static function group(): string
    {
        return 'systeme';
    }
}
