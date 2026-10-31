<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

/**
 * Provides RH position identifiers for a user without exposing RH models to Socle.
 */
interface AuthorizationPositionResolverContract
{
    /** @return list<int> */
    public function positionIdsForUser(int $userId): array;
}
