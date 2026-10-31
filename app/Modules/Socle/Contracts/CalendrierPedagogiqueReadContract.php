<?php

namespace App\Modules\Socle\Contracts;

interface CalendrierPedagogiqueReadContract
{
    /** Date intervals excluding teaching, including recurring holidays. */
    public function exclusions(int $niveauId): array;
}
