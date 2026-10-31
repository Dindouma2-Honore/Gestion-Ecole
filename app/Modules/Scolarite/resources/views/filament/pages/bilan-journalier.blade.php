<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2">
        <label class="grid gap-2 text-sm font-medium">
            Journée
            <input type="date" wire:model.live="date" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
        </label>
        <div class="flex items-end md:justify-end">
            <x-loading-button wire:click="exporter" target="exporter" variant="primary" loadingText="Exportation...">
                Exporter CSV
            </x-loading-button>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <x-filament::section>
            <p class="text-sm text-gray-500">Total encaissé</p>
            <p class="mt-2 text-2xl font-bold text-success-600">{{ number_format($bilan->total, 0, ',', ' ') }} FCFA</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">Total annulé</p>
            <p class="mt-2 text-2xl font-bold text-danger-600">{{ number_format($bilan->total_annule, 0, ',', ' ') }} FCFA</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">Net journalier</p>
            <p class="mt-2 text-2xl font-bold text-primary-600">{{ number_format($bilan->net, 0, ',', ' ') }} FCFA</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">Paiements / Annulations</p>
            <p class="mt-2 text-2xl font-bold text-gray-700 dark:text-gray-200">{{ $bilan->nombre_paiements }} <span class="text-sm text-danger-500">({{ $bilan->nombre_annulations }} annulés)</span></p>
        </x-filament::section>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Totaux par type de frais</x-slot>
            @forelse($bilan->totaux_par_type as $type => $montant)
                <div class="flex justify-between border-b py-2"><span>{{ $type }}</span><strong>{{ number_format($montant, 0, ',', ' ') }} FCFA</strong></div>
            @empty <p class="text-sm text-gray-500">Aucun encaissement scolaire validé pour cette journée.</p> @endforelse
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Totaux par mode de paiement</x-slot>
            @forelse($bilan->totaux_par_mode as $mode => $montant)
                <div class="flex justify-between border-b py-2"><span>{{ str($mode)->replace('_', ' ')->headline() }}</span><strong>{{ number_format($montant, 0, ',', ' ') }} FCFA</strong></div>
            @empty <p class="text-sm text-gray-500">Aucun encaissement scolaire validé pour cette journée.</p> @endforelse
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Enregistrements de la journée</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b"><th class="p-3">Heure</th><th class="p-3">Reçu</th><th class="p-3">Type de frais</th><th class="p-3">Mode</th><th class="p-3">Statut</th><th class="p-3 text-right">Montant</th></tr></thead>
                <tbody>
                @forelse ($bilan->paiements as $paiement)
                    <tr class="border-b @if($paiement->statut === 'annule') opacity-60 bg-danger-50/20 dark:bg-danger-950/20 @endif">
                        <td class="whitespace-nowrap p-3">{{ $paiement->created_at->format('H:i') }}</td>
                        <td class="p-3 font-medium">{{ $paiement->numero_recu }}</td>
                        <td class="p-3">{{ $paiement->type_frais_libelle }}</td>
                        <td class="p-3">{{ str($paiement->mode)->replace('_', ' ')->headline() }}</td>
                        <td class="p-3">
                            @if($paiement->statut === 'valide')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900/30 dark:text-success-400">Validé</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900/30 dark:text-danger-400">Annulé</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap p-3 text-right font-semibold @if($paiement->statut === 'annule') line-through text-danger-600 @endif">{{ number_format((float) $paiement->montant, 0, ',', ' ') }} FCFA</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">Aucun enregistrement scolaire pour cette journée.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
