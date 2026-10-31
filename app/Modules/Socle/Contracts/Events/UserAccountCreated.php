<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** A new staff user row has been created and linked to personnel. */
final readonly class UserAccountCreated implements ShouldDispatchAfterCommit
{
    public function __construct(public int $userId) {}
}
