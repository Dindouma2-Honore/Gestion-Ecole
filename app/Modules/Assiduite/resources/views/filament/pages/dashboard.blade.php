<x-filament-panels::page>
    @php($indicateurs = $this->indicateurs())

    <div class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">Situation consolidée du {{ now()->translatedFormat('d F Y') }}</p>

        <div class="amb-module-indicators grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['label' => 'Présents', 'value' => $indicateurs['presents'], 'icon' => 'heroicon-o-check-circle', 'color' => 'text-success-600'],
                ['label' => 'Retards', 'value' => $indicateurs['retards'], 'icon' => 'heroicon-o-clock', 'color' => 'text-warning-600'],
                ['label' => 'Absences', 'value' => $indicateurs['absences'], 'icon' => 'heroicon-o-user-minus', 'color' => 'text-danger-600'],
                ['label' => 'Appels à vérifier', 'value' => $indicateurs['a_verifier'], 'icon' => 'heroicon-o-question-mark-circle', 'color' => 'text-warning-600'],
                ['label' => 'Anomalies ouvertes', 'value' => $indicateurs['anomalies'], 'icon' => 'heroicon-o-exclamation-triangle', 'color' => 'text-danger-600'],
                ['label' => 'Justifications en attente', 'value' => $indicateurs['justifications'], 'icon' => 'heroicon-o-document-check', 'color' => 'text-primary-600'],
            ] as $carte)
                <x-module-stat :label="$carte['label']" :value="number_format($carte['value'], 0, ',', ' ')" :icon="$carte['icon']" :color="str_replace(['text-', '-600'], '', $carte['color'])" />
            @endforeach
        </div>


    </div>
</x-filament-panels::page>
