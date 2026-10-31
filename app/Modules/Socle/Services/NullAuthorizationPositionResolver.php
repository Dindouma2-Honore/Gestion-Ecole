<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\AuthorizationPositionResolverContract;

final class NullAuthorizationPositionResolver implements AuthorizationPositionResolverContract
{
    public function positionIdsForUser(int $userId): array
    {
        return [];
    }
}
