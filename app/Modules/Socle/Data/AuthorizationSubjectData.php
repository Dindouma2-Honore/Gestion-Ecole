<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

use App\Modules\Socle\Enums\AuthorizationSubjectType;

readonly class AuthorizationSubjectData
{
    public function __construct(
        public AuthorizationSubjectType $type,
        public int $id,
    ) {}
}
