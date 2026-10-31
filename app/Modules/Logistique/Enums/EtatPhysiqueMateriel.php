<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Enums;

enum EtatPhysiqueMateriel: string
{
    case Bon = 'bon';
    case Moyen = 'moyen';
    case Mauvais = 'mauvais';
    case HorsService = 'hors_service';
    case AReparer = 'a_reparer'; // Legacy / transitional state awaiting LOT 4B operational status
    case MisAuRebut = 'mis_au_rebut'; // Legacy / transitional lifecycle state awaiting LOT 4B

    public function label(): string
    {
        return match ($this) {
            self::Bon => 'Bon',
            self::Moyen => 'Moyen',
            self::Mauvais => 'Mauvais',
            self::HorsService => 'Hors service',
            self::AReparer => 'À réparer (legacy)',
            self::MisAuRebut => 'Mis au rebut (legacy)',
        };
    }

    public static function options(): array
    {
        return [
            self::Bon->value => 'Bon',
            self::Moyen->value => 'Moyen',
            self::Mauvais->value => 'Mauvais',
            self::HorsService->value => 'Hors service',
            self::AReparer->value => 'À réparer (legacy)',
            self::MisAuRebut->value => 'Mis au rebut (legacy)',
        ];
    }

    public static function valider(string $valeur): bool
    {
        return self::tryFrom($valeur) !== null;
    }
}
