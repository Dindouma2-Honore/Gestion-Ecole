<x-filament::section class="mt-6">
    <x-slot name="heading">Historique des paiements</x-slot>
    <x-slot name="description">Tous les paiements sont conservés, y compris ceux annulés.</x-slot>

    <div class="mb-4 grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl bg-primary-50 p-4 dark:bg-primary-950/30"><p class="text-xs uppercase text-gray-500">Frais de scolarité dus</p><p class="text-lg font-bold">{{ number_format($montantsParGroupe['scolarite'] ?? 0, 0, ',', ' ') }} FCFA</p></div>
        <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-900"><p class="text-xs uppercase text-gray-500">Autres frais dus</p><p class="text-lg font-bold">{{ number_format($montantsParGroupe['autres'] ?? 0, 0, ',', ' ') }} FCFA</p></div>
    </div>

    @forelse ($paiements as $paiement)
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 p-3 last:mb-0 dark:border-white/10">
            <div>
                <strong>{{ $paiement['numero_recu'] }}</strong>
                <div class="text-sm text-gray-500">
                    {{ $paiement['date']->format('d/m/Y H:i') }} · {{ number_format($paiement['montant'], 0, ',', ' ') }} FCFA
                </div>
            </div>
            <span @class([
                'font-semibold',
                'text-danger-600' => $paiement['statut'] === 'annule',
                'text-success-600' => $paiement['statut'] !== 'annule',
            ])>
                {{ $paiement['statut'] === 'annule' ? 'Annulé' : 'Validé' }}
            </span>
            @if ($paiement['url_recu'])
                <x-filament::button tag="a" :href="$paiement['url_recu']" icon="heroicon-o-arrow-down-tray" size="sm">
                    Télécharger le reçu
                </x-filament::button>
            @else
                <span class="text-sm text-gray-400">Reçu indisponible</span>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">Aucun paiement enregistré.</p>
    @endforelse
</x-filament::section>
