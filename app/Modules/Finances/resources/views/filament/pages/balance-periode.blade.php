<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section icon="heroicon-o-funnel" icon-color="primary">
            <x-slot name="heading">Périmètre de la balance</x-slot>
            <x-slot name="description">Les montants se recalculent automatiquement selon vos filtres.</x-slot>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([['dateDebut', 'Du'], ['dateFin', 'Au']] as [$champ, $libelle])
                    <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">{{ $libelle }}
                        <input type="date" wire:model.live="{{ $champ }}" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    </label>
                @endforeach
                <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Module
                    <select wire:model.live="moduleOrigine" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Tous les modules</option>
                        @foreach ($this->modulesDisponibles() as $module)<option value="{{ $module }}">{{ $module }}</option>@endforeach
                    </select>
                </label>
                <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Mouvement
                    <select wire:model.live="typeMouvement" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Entrées et sorties</option><option value="encaissement">Entrées uniquement</option><option value="decaissement">Sorties uniquement</option>
                    </select>
                </label>
            </div>
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
                <p class="text-sm text-gray-500">{{ $balance->nombre_operations }} {{ $balance->nombre_operations > 1 ? 'opérations trouvées' : 'opération trouvée' }}</p>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button wire:click="reinitialiserFiltres" color="gray" icon="heroicon-o-arrow-path">Aujourd’hui</x-filament::button>
                    <x-loading-button wire:click="exporter" target="exporter" variant="primary" loadingText="Exportation...">Exporter en CSV</x-loading-button>
                </div>
            </div>
        </x-filament::section>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([
                ['Entrées', 'Sommes encaissées', $balance->total_entrees, 'heroicon-o-arrow-trending-up', 'success'],
                ['Sorties', 'Sommes décaissées', $balance->total_sorties, 'heroicon-o-arrow-trending-down', 'danger'],
                ['Solde net', 'Entrées moins sorties', $balance->solde_net, 'heroicon-o-scale', $balance->solde_net >= 0 ? 'primary' : 'danger'],
            ] as [$titre, $description, $montant, $icone, $couleur])
                <x-filament::section compact>
                    <div class="flex items-start justify-between gap-4">
                        <div><p class="text-sm font-medium text-gray-500">{{ $titre }}</p><p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($montant, 0, ',', ' ') }} <span class="text-sm text-gray-500">FCFA</span></p><p class="mt-1 text-xs text-gray-500">{{ $description }}</p></div>
                        <div @class(['rounded-xl p-3', 'bg-success-50 text-success-600 dark:bg-success-500/10' => $couleur === 'success', 'bg-danger-50 text-danger-600 dark:bg-danger-500/10' => $couleur === 'danger', 'bg-primary-50 text-primary-600 dark:bg-primary-500/10' => $couleur === 'primary'])><x-filament::icon :icon="$icone" class="h-6 w-6" /></div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <x-filament::section compact icon="heroicon-o-academic-cap" icon-color="primary"><p class="text-sm text-gray-500">Recettes de scolarité</p><p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ number_format($balance->recettes_par_groupe['scolarite'], 0, ',', ' ') }} FCFA</p></x-filament::section>
            <x-filament::section compact icon="heroicon-o-squares-plus" icon-color="info"><p class="text-sm text-gray-500">Autres recettes</p><p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ number_format($balance->recettes_par_groupe['autres'], 0, ',', ' ') }} FCFA</p></x-filament::section>
        </div>

        <x-filament::section icon="heroicon-o-chart-bar-square">
            <x-slot name="heading">Répartition par activité</x-slot><x-slot name="description">Synthèse par module et sous-module.</x-slot>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="px-4 py-3">Module</th><th class="px-4 py-3">Activité</th><th class="px-4 py-3 text-right">Entrées</th><th class="px-4 py-3 text-right">Sorties</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($balance->par_module as $module => $donnees)
                    @foreach ($donnees['sous_modules'] as $sousModule => $totaux)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5"><td class="px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ $module }}</td><td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $sousModule }}</td><td class="px-4 py-3 text-right font-semibold text-success-600">{{ $totaux['encaissements'] > 0 ? number_format($totaux['encaissements'], 0, ',', ' ').' FCFA' : '—' }}</td><td class="px-4 py-3 text-right font-semibold text-danger-600">{{ $totaux['decaissements'] > 0 ? number_format($totaux['decaissements'], 0, ',', ' ').' FCFA' : '—' }}</td></tr>
                    @endforeach
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-gray-500">Aucune activité pour les filtres sélectionnés.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </x-filament::section>

        <x-filament::section icon="heroicon-o-list-bullet">
            <x-slot name="heading">Journal des opérations</x-slot><x-slot name="description">Chaque ligne correspond à un mouvement unique de caisse.</x-slot>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="px-4 py-3">Sens</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Origine</th><th class="px-4 py-3">Référence</th><th class="px-4 py-3 text-right">Montant</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($balance->detail_entrees as $operation)
                    <tr class="hover:bg-success-50/50 dark:hover:bg-success-500/5"><td class="px-4 py-3"><x-filament::badge color="success" icon="heroicon-o-arrow-down-left">Entrée</x-filament::badge></td><td class="whitespace-nowrap px-4 py-3">{{ $operation->created_at->format('d/m/Y à H:i') }}</td><td class="px-4 py-3"><strong>{{ $operation->module_origine ?? 'Non classé' }}</strong><br><span class="text-xs text-gray-500">{{ $operation->sous_module ?? 'Non classé' }}</span></td><td class="px-4 py-3">{{ $operation->reference_type ?? 'Mouvement' }} #{{ $operation->reference_id ?? $operation->id }}</td><td class="whitespace-nowrap px-4 py-3 text-right font-bold text-success-600">+ {{ number_format((float) $operation->montant, 0, ',', ' ') }} FCFA</td></tr>
                @endforeach
                @foreach ($balance->detail_sorties as $operation)
                    <tr class="hover:bg-danger-50/50 dark:hover:bg-danger-500/5"><td class="px-4 py-3"><x-filament::badge color="danger" icon="heroicon-o-arrow-up-right">Sortie</x-filament::badge></td><td class="whitespace-nowrap px-4 py-3">{{ $operation->created_at->format('d/m/Y à H:i') }}</td><td class="px-4 py-3"><strong>{{ $operation->module_origine ?? 'Non classé' }}</strong><br><span class="text-xs text-gray-500">{{ $operation->sous_module ?? 'Non classé' }}</span></td><td class="px-4 py-3">{{ $operation->reference_type ?? 'Mouvement' }} #{{ $operation->reference_id ?? $operation->id }}</td><td class="whitespace-nowrap px-4 py-3 text-right font-bold text-danger-600">− {{ number_format((float) $operation->montant, 0, ',', ' ') }} FCFA</td></tr>
                @endforeach
                @if ($balance->nombre_operations === 0)<tr><td colspan="5" class="px-4 py-12 text-center text-gray-500"><x-filament::icon icon="heroicon-o-inbox" class="mx-auto mb-3 h-8 w-8" /><strong class="block text-gray-950 dark:text-white">Aucun mouvement</strong><span>Modifiez la période ou réinitialisez les filtres.</span></td></tr>@endif
                </tbody>
            </table></div>
        </x-filament::section>

        <x-filament::section icon="heroicon-o-clock" icon-color="warning" collapsible collapsed>
            <x-slot name="heading">Sorties en attente de validation</x-slot>
            @if ($erreur)<p class="text-sm text-warning-600">{{ $erreur }}</p>@else
                @forelse ($attente as $depense)<div class="flex gap-3 border-b py-3 last:border-0 sm:justify-between"><span>{{ $depense->libelle ?? $depense->reference ?? 'Dépense #'.$depense->id }}</span><strong class="whitespace-nowrap text-warning-600">{{ number_format((float) $depense->montant, 0, ',', ' ') }} FCFA</strong></div>@empty<p class="text-sm text-gray-500">Aucune sortie en attente.</p>@endforelse
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
