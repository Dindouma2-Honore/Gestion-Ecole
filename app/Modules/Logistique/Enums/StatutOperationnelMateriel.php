<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Enums;

enum StatutOperationnelMateriel: string
{
    case EnStock = 'en_stock';
    case Affecte = 'affecte';
    case EnMaintenance = 'en_maintenance';
    case Loue = 'loue';
    case Reforme = 'reforme';

    public function label(): string
    {
        return match ($this) {
            self::EnStock => 'En stock (disponible)',
            self::Affecte => 'Affecté',
            self::EnMaintenance => 'En maintenance',
            self::Loue => 'Loué',
            self::Reforme => 'Réformé / Mis au rebut',
        };
    }

    public static function options(): array
    {
        return [
            self::EnStock->value => 'En stock (disponible)',
            self::Affecte->value => 'Affecté',
            self::EnMaintenance->value => 'En maintenance',
            self::Loue->value => 'Loué',
            self::Reforme->value => 'Réformé / Mis au rebut',
        ];
    }

    public static function valider(string $valeur): bool
    {
        return self::tryFrom($valeur) !== null;
    }
}
