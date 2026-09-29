<?php

declare(strict_types=1);

namespace App\Modules\Socle\Http\Controllers;

use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Illuminate\Contracts\View\View;
use Throwable;

class PeriodesScolairesController
{
    public function __invoke(AnneeScolaireServiceContract $annees): View
    {
        try {
            $annee = $annees->getAnneeCourante();
            $periodes = $annees->getPeriodes((int) $annee->id);
        } catch (Throwable) {
            $annee = null;
            $periodes = [];
        }

        return view('socle::periodes-scolaires', compact('annee', 'periodes'));
    }
}
