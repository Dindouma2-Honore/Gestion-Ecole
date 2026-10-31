<div class="space-y-6">
    <x-filament::section icon="heroicon-o-funnel" icon-color="primary">
        <x-slot name="heading">Sélection de la classe</x-slot>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                Année scolaire
                <select wire:model.live="anneeScolaireId" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <option value="">Choisir une année</option>
                    @foreach($this->getAnneesScolaires() as $id => $libelle)<option value="{{ $id }}">{{ $libelle }}</option>@endforeach
                </select>
            </label>
            <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                Cycle
                <select wire:model.live="cycleFiltre" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <option value="">Choisir un cycle</option>
                    @foreach($cycles as $cycle)<option value="{{ $cycle }}">{{ $cycle }}</option>@endforeach
                </select>
            </label>
            <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                Niveau
                <select wire:model.live="niveauFiltre" @disabled(! $cycleFiltre) class="rounded-lg border-gray-300 bg-white shadow-sm disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900">
                    <option value="">Choisir un niveau</option>
                    @foreach($niveaux as $niveau)<option value="{{ $niveau }}">{{ $niveau }}</option>@endforeach
                </select>
            </label>
            <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                Classe
                <select wire:model.live="classeId" @disabled(! $niveauFiltre) class="rounded-lg border-gray-300 bg-white shadow-sm disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900">
                    <option value="">Choisir une classe</option>
                    @foreach($classes as $classe)<option value="{{ $classe->id }}">{{ $classe->nom }}</option>@endforeach
                </select>
            </label>
        </div>
    </x-filament::section>

    @if($classeId)
        @php
            $planningVerrouille = in_array($statutPlanning, ['valide', 'publie'], true);
            $libellesStatuts = ['preparation' => 'En préparation', 'brouillon' => 'En préparation', 'genere' => 'Généré', 'ajuste' => 'Ajusté', 'valide' => 'Validé', 'publie' => 'Publié'];
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600 dark:text-gray-300">État du planning : <span class="font-semibold text-gray-950 dark:text-white">{{ $libellesStatuts[$statutPlanning] ?? $statutPlanning }}</span></p>
            <div class="flex flex-wrap gap-2">
                @if(! $planningVerrouille)
                    <x-filament::button wire:click="genererEmploiDuTemps" wire:loading.attr="disabled" icon="heroicon-o-sparkles" color="gray">Générer automatiquement</x-filament::button>
                    <x-filament::button wire:click="verifierConflits" wire:loading.attr="disabled" icon="heroicon-o-magnifying-glass" color="gray">Vérifier les conflits</x-filament::button>
                    <x-filament::button wire:click="validerPlanning" wire:loading.attr="disabled" icon="heroicon-o-check-badge">Valider</x-filament::button>
                @elseif($statutPlanning === 'valide')
                    <x-filament::button wire:click="publierPlanning" wire:loading.attr="disabled" icon="heroicon-o-paper-airplane" color="success">Publier</x-filament::button>
                @endif
                <x-filament::button wire:click="imprimer" wire:loading.attr="disabled" icon="heroicon-o-printer" color="gray">Imprimer</x-filament::button>
                <x-filament::button wire:click="exporterPdf" wire:loading.attr="disabled" icon="heroicon-o-document-arrow-down" color="gray">Exporter PDF</x-filament::button>
            </div>
        </div>
        <x-filament::section heading="Volumes horaires hebdomadaires">
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5">
                        <tr><th class="p-3">Matière</th><th class="p-3">Enseignant</th><th class="p-3">Prévu</th><th class="p-3">Placé</th><th class="p-3">Reste</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse($synthese as $ligne)
                            <tr class="hover:bg-primary-50/50 dark:hover:bg-white/5">
                                <td class="p-3 font-medium text-gray-950 dark:text-white">{{ $ligne->matiere }}</td>
                                <td class="p-3">{{ $ligne->enseignants->isNotEmpty() ? $ligne->enseignants->implode(', ') : '—' }}</td>
                                <td class="p-3">{{ rtrim(rtrim(number_format($ligne->prevu, 2, '.', ''), '0'), '.') }} h</td>
                                <td class="p-3">{{ rtrim(rtrim(number_format($ligne->place, 2, '.', ''), '0'), '.') }} h</td>
                                <td class="p-3">{{ rtrim(rtrim(number_format($ligne->reste, 2, '.', ''), '0'), '.') }} h</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-8 text-center text-gray-500">Aucune offre pédagogique pour cette classe.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @else
        <x-filament::section><p class="text-sm text-gray-500 dark:text-gray-400">Sélectionnez une classe pour consulter les volumes prévus et placés.</p></x-filament::section>
    @endif
</div>
