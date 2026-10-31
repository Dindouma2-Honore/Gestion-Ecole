<?php

namespace App\Modules\Pedagogie\Enums;

enum TypeAffectationPedagogique: string
{
    case Principal = 'principal';
    case Assistant = 'assistant';
    case Matiere = 'matiere';

    public function label(): string
    {
        return match ($this) {
            self::Principal => 'Enseignant principal',self::Assistant => 'Assistant',self::Matiere => 'Enseignant de matière'
        };
    }

    public static function options(): array
    {
        return array_column(array_map(fn ($v) => ['id' => $v->value, 'label' => $v->label()], self::cases()), 'label', 'id');
    }
}
