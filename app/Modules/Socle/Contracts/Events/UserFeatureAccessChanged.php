<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** A user, role or group assignment changed; not a claim about the final effective access. */
final readonly class UserFeatureAccessChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $userId,
        public string $featureCode,
        public string $featureName,
        public string $category,
        public bool $granted,
        public string $occurredAt,
        public ?string $groupName = null,
    ) {}
}
