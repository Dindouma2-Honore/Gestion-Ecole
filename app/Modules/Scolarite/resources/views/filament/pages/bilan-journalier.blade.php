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

    <div class="grid gap-4 md:grid-cols-2">
        <x-filament::section>
            <p class="text-sm text-gray-500">Total encaissé</p>
            <p class="mt-2 text-2xl font-bold text-success-600">{{ number_format($bilan->total, 0, ',', ' ') }} FCFA</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">Nombre d’enregistrements</p>
            <p class="mt-2 text-2xl font-bold text-primary-600">{{ $bilan->nombre_paiements }}</p>
        </x-filament::section>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Totaux par type de frais</x-slot>
            @forelse($bilan->totaux_par_type as $type => $montant)
                <div class="flex justify-between border-b py-2"><span>{{ $type }}</span><strong>{{ number_format($montant, 0, ',', ' ') }} FCFA</strong></div>
            @empty <p class="text-sm text-gray-500">Aucun encaissement scolaire pour cette journée.</p> @endforelse
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Totaux par mode de paiement</x-slot>
            @forelse($bilan->totaux_par_mode as $mode => $montant)
                <div class="flex justify-between border-b py-2"><span>{{ str($mode)->replace('_', ' ')->headline() }}</span><strong>{{ number_format($montant, 0, ',', ' ') }} FCFA</strong></div>
            @empty <p class="text-sm text-gray-500">Aucun encaissement scolaire pour cette journée.</p> @endforelse
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Enregistrements de la journée</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b"><th class="p-3">Heure</th><th class="p-3">Reçu</th><th class="p-3">Type de frais</th><th class="p-3">Mode</th><th class="p-3 text-right">Montant</th></tr></thead>
                <tbody>
                @forelse ($bilan->paiements as $paiement)
                    <tr class="border-b"><td class="whitespace-nowrap p-3">{{ $paiement->created_at->format('H:i') }}</td><td class="p-3">{{ $paiement->numero_recu }}</td><td class="p-3">{{ $paiement->type_frais_libelle }}</td><td class="p-3">{{ str($paiement->mode)->replace('_', ' ')->headline() }}</td><td class="whitespace-nowrap p-3 text-right font-semibold">{{ number_format((float) $paiement->montant, 0, ',', ' ') }} FCFA</td></tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-gray-500">Aucun enregistrement scolaire pour cette journée.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
