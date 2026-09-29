<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Support;

final class ElevePhoto
{
    public const MAX_SIZE_KO = 5120;

    public const MIME_TYPES = ['image/jpeg', 'image/png'];

    public const EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /** @return array<int, string> */
    public static function validationRules(): array
    {
        return [
            'image',
            'mimes:'.implode(',', self::EXTENSIONS),
            'max:'.self::MAX_SIZE_KO,
        ];
    }
}
