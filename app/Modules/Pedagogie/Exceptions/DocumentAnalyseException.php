<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Exceptions;

use RuntimeException;

class DocumentAnalyseException extends RuntimeException
{
    public static function formatNonSupporte(string $extension): self
    {
        return new self("Format de fichier [{$extension}] non supporté. Les formats acceptés sont PDF, DOCX et DOC.");
    }

    public static function documentVideOuIllisible(): self
    {
        return new self('Le document ne contient aucun texte exploitable. Il semble s\'agir d\'une image scannée sans couche OCR ou d\'un fichier protégé.');
    }

    public static function aucunElementIdentifie(): self
    {
        return new self('Aucune structure pédagogique (chapitre, domaine, unité, leçon ou compétence) n\'a pu être identifiée.');
    }
}
