<x-filament-widgets::widget>
    <x-filament::section heading="Consommation budgétaire">
        @if (! $budget)
            <p class="text-sm text-gray-500">Aucun budget n’est défini pour l’année scolaire active.</p>
        @else
            <div class="space-y-5">
                @foreach ($lignes as $ligne)
                    <div>
                        <div class="mb-2 flex flex-col gap-1 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                            <strong>{{ $ligne['nom'] }}</strong>
                            @if ($ligne['disponible'])
                                <span>{{ number_format($ligne['montant_consomme'], 0, ',', ' ') }} / {{ number_format($ligne['montant_prevu'], 0, ',', ' ') }} FCFA</span>
                            @else
                                <span class="text-gray-500">Consommation disponible après E44</span>
                            @endif
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                            <div @class(['h-full rounded-full', 'bg-danger-600' => $ligne['depassement'], 'bg-primary-600' => ! $ligne['depassement']])
                                 style="width: {{ min(100, $ligne['pourcentage_consomme']) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
