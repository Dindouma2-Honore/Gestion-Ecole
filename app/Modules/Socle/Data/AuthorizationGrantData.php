<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

use App\Modules\Socle\Enums\AuthorizationAction;

/** @phpstan-type AuthorizationActions list<AuthorizationAction> */
readonly class AuthorizationGrantData
{
    /** @param list<AuthorizationAction> $actions */
    public function __construct(
        public AuthorizationSubjectData $subject,
        public string $featureCode,
        public array $actions,
    ) {}
}
