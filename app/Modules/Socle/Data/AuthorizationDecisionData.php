<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

use App\Modules\Socle\Enums\AuthorizationAction;

readonly class AuthorizationDecisionData
{
    /** @param list<AuthorizationSubjectData> $grantedBy */
    public function __construct(
        public bool $allowed,
        public string $featureCode,
        public AuthorizationAction $action,
        public array $grantedBy = [],
    ) {}
}
