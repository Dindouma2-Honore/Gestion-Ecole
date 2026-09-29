<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Http\Controllers;

use App\Modules\Scolarite\Contracts\FraisServiceContract;
use App\Modules\Scolarite\Models\FactureGeneree;
use Illuminate\View\View;

class ImprimerFactureController
{
    public function __invoke(FactureGeneree $facture): View
    {
        $facture->load(['inscription.eleve', 'inscription.classe']);

        $detailFrais = app(FraisServiceContract::class)->getDetailFrais($facture->inscription_id);

        return view('scolarite::filament.facture-impression', compact('facture', 'detailFrais'));
    }
}
