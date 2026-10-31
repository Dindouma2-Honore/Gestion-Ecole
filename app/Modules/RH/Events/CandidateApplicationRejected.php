<?php

declare(strict_types=1);

namespace App\Modules\RH\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class CandidateApplicationRejected implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $candidatureId,
        public string $candidateName,
        public string $position,
        public ?string $phone = null,
    ) {}
}
