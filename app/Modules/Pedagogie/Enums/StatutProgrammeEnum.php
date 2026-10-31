<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Enums;

enum StatutProgrammeEnum: string
{
    case ANALYSE_EN_ATTENTE = 'analyse_en_attente';
    case ANALYSE_EN_COURS = 'analyse_en_cours';
    case A_VALIDER = 'a_valider';
    case BROUILLON = 'brouillon';
    case SOUMIS = 'soumis';
    case VALIDE = 'valide';
    case REJETE = 'rejete';
    case PUBLIE = 'publie';
    case ECHEC_ANALYSE = 'echec_analyse';
    case ARCHIVE = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::ANALYSE_EN_ATTENTE => 'Analyse en attente',
            self::ANALYSE_EN_COURS => 'Analyse en cours',
            self::A_VALIDER => 'À valider par l\'enseignant',
            self::BROUILLON => 'Brouillon',
            self::SOUMIS => 'Soumis à la direction',
            self::VALIDE => 'Validé',
            self::REJETE => 'Rejeté',
            self::PUBLIE => 'Publié',
            self::ECHEC_ANALYSE => 'Échec de l\'analyse',
            self::ARCHIVE => 'Archivé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ANALYSE_EN_ATTENTE, self::BROUILLON => 'gray',
            self::ANALYSE_EN_COURS => 'info',
            self::A_VALIDER => 'warning',
            self::SOUMIS => 'primary',
            self::VALIDE, self::PUBLIE => 'success',
            self::REJETE, self::ECHEC_ANALYSE => 'danger',
            self::ARCHIVE => 'secondary',
        };
    }
}
