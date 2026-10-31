<?php

namespace App\Modules\RH\Contracts;

interface EnseignantsPedagogiquesReadContract
{
    /** Teacher identity and active status keyed by teacher id. */
    public function tous(): array;
}
