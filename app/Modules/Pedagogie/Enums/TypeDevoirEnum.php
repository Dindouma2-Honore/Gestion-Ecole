<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Enums;

enum TypeDevoirEnum: string
{
    case EXERCICE = 'exercice';
    case LECTURE = 'lecture';
    case DEVOIR_SURVEILLE = 'devoir_surveille';
    case PROJET = 'projet';
    case RECHERCHE = 'recherche';

    public function label(): string
    {
        return match ($this) {
            self::EXERCICE => 'Exercice d\'application',
            self::LECTURE => 'Lecture / Révision',
            self::DEVOIR_SURVEILLE => 'Devoir surveillé',
            self::PROJET => 'Projet scolaire',
            self::RECHERCHE => 'Recherche / Exposé',
        };
    }
}
