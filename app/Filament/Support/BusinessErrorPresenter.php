<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Modules\Scolarite\Exceptions\CapaciteClasseDepasseeException;
use App\Modules\Scolarite\Exceptions\DoubleInscriptionException;
use App\Modules\Scolarite\Exceptions\ParentPayeurInvalideException;
use DomainException;
use Throwable;

final class BusinessErrorPresenter
{
    public static function supports(Throwable $exception): bool
    {
        return $exception instanceof DomainException
            || (str_starts_with($exception::class, 'App\\Modules\\')
                && str_contains($exception::class, '\\Exceptions\\'));
    }

    public static function title(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof DoubleInscriptionException => 'Inscription déjà existante',
            $exception instanceof CapaciteClasseDepasseeException => 'Classe complète',
            $exception instanceof ParentPayeurInvalideException => 'Parent payeur requis',
            default => 'Opération impossible',
        };
    }

    private function __construct() {}
}
