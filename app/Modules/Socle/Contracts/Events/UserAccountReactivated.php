<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** An existing placeholder or disabled staff account has been reused; its ID is unchanged. */
final readonly class UserAccountReactivated implements ShouldDispatchAfterCommit
{
    public function __construct(public int $userId) {}
}
