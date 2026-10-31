<?php

namespace App\Modules\Scolarite\Contracts;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class EnrollmentValidated implements ShouldDispatchAfterCommit
{
    public function __construct(public int $inscriptionId, public ?string $parentTelephone) {}
}
