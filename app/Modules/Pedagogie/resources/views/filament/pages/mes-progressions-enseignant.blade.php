<x-filament-panels::page>
    <div class="space-y-6">
        @if($this->isAdministrativeViewer())
            <x-filament::section icon="heroicon-o-funnel" icon-color="primary">
                <x-slot name="heading">Filtres des progressions</x-slot>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Année scolaire
                        <select wire:model.live="anneeScolaireId" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            @foreach($this->getAnneesScolaires() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Cycle
                        <select wire:model.live="cycleId" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <option value="">Tous les cycles</option>
                            @foreach($this->getCycles() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Niveau
                        <select wire:model.live="niveauId" @disabled(! $cycleId) class="rounded-lg border-gray-300 bg-white shadow-sm disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900">
                            <option value="">Tous les niveaux</option>
                            @foreach($this->getNiveaux() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Classe
                        <select wire:model.live="classeId" @disabled(! $niveauId) class="rounded-lg border-gray-300 bg-white shadow-sm disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900">
                            <option value="">Toutes les classes</option>
                            @foreach($this->getClasses() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Enseignant
                        <select wire:model.live="enseignantFiltreId" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <option value="">Tous les enseignants</option>
                            @foreach($this->getEnseignants() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Matière
                        <select wire:model.live="matiereId" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <option value="">Toutes les matières</option>
                            @foreach($this->getMatieres() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">État de progression
                        <select wire:model.live="etatProgression" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <option value="">Tous les états</option>
                            <option value="sans_progression">Pas encore commencée</option>
                            <option value="en_cours">En cours</option>
                            <option value="terminee">Terminée</option>
                        </select>
                    </label>
                </div>
            </x-filament::section>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Les offres affichées correspondent à vos affectations actives de l’année scolaire sélectionnée.</p>
        @endif

        {{ $this->table }}
    </div>
    <x-filament-actions::modals />
</x-filament-panels::page>
