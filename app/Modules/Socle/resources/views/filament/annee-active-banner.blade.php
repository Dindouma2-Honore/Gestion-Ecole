@php
    try {
        $anneeCourante = app(\App\Modules\Socle\Contracts\AnneeScolaireServiceContract::class)->getAnneeCourante();
    } catch (\App\Modules\Socle\Exceptions\AucuneAnneeActiveException) {
        $anneeCourante = null;
    }
@endphp

<div class="mb-6 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-200">
    @if ($anneeCourante)
        Année scolaire active : <strong>{{ $anneeCourante->libelle }}</strong>
    @else
        <strong>Aucune année scolaire active.</strong> Le Fondateur doit en activer une avant toute saisie métier.
    @endif
</div>
