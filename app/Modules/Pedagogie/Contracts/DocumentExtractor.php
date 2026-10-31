<?php

namespace App\Modules\Pedagogie\Contracts;

interface DocumentExtractor
{
    public function extraire(string $chemin, string $extension): string;
}
