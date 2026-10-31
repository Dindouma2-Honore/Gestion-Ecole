<?php

namespace App\Modules\Pedagogie\Contracts;

interface ProgramDocumentAnalyzer
{
    /** Flat ordered elements. parent_index references an earlier element in this proposal, never a database ID. */
    public function analyser(string $texte): array;
}
