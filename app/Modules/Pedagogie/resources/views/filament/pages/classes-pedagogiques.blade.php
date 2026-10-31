<x-filament-panels::page>
    @php
        $hierarchie = $this->getClassesParCycle();
        $cycles = $hierarchie->keys();
        $niveaux = $cycleFiltre ? $hierarchie->get($cycleFiltre, collect())->keys() : collect();
        $classesDisponibles = $cycleFiltre && $niveauFiltre
            ? $hierarchie->get($cycleFiltre, collect())->get($niveauFiltre, collect())
            : collect();
        $classe = $this->getClasseSelectionnee();
        $matieres = $classe ? $this->getMatieresEtEnseignants($classe) : collect();
        $emplois = $classe ? $this->getEmploiDuTemps($classe) : collect();
    @endphp

    <div class="space-y-6">
        <x-filament::section icon="heroicon-o-funnel" icon-color="primary">
            <x-slot name="heading">Sélection de la classe</x-slot>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                    Année scolaire
                    <select wire:model.live="anneeScolaireId" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Choisir une année</option>
                        @foreach($this->getAnneesScolaires() as $id => $libelle)
                            <option value="{{ $id }}">{{ $libelle }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                    Cycle
                    <select wire:model.live="cycleFiltre" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Choisir un cycle</option>
                        @foreach($cycles as $cycle)
                            <option value="{{ $cycle }}">{{ $cycle }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                    Niveau
                    <select wire:model.live="niveauFiltre" @disabled(! $cycleFiltre) class="rounded-lg border-gray-300 bg-white shadow-sm disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Choisir un niveau</option>
                        @foreach($niveaux as $niveau)
                            <option value="{{ $niveau }}">{{ $niveau }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">
                    Classe
                    <select wire:model.live="classeId" @disabled(! $niveauFiltre) class="rounded-lg border-gray-300 bg-white shadow-sm disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Choisir une classe</option>
                        @foreach($classesDisponibles as $item)
                            <option value="{{ $item->id }}">{{ $item->nom }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </x-filament::section>

        @if($classe)
            <x-filament::section :heading="$classe->nom" description="Informations générales">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">Année scolaire</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $classe->annee_libelle }}</dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">Cycle</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $classe->cycle_nom }}</dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">Niveau</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $classe->niveau_nom }}</dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">Classe</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $classe->nom }}</dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">Effectif</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $classe->effectif }}</dd></div>
                    @if($classe->enseignant_principal)
                        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Enseignant principal</dt><dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $classe->enseignant_principal }}</dd></div>
                    @endif
                </dl>
            </x-filament::section>

            <x-filament::section heading="Matières & enseignants">
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5">
                            <tr><th class="p-3">Matière</th><th class="p-3">Enseignant(s)</th><th class="p-3">Volume / semaine</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @forelse($matieres as $offre)
                                <tr class="hover:bg-primary-50/50 dark:hover:bg-white/5">
                                    <td class="p-3 font-medium text-gray-950 dark:text-white">{{ $offre->matiere_nom }}</td>
                                    <td class="p-3">{{ $offre->enseignants->isNotEmpty() ? $offre->enseignants->implode(', ') : '—' }}</td>
                                    <td class="p-3">{{ $offre->heures_hebdomadaires !== null ? $offre->heures_hebdomadaires.' h' : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="p-8 text-center text-gray-500">Aucune offre pédagogique pour cette classe.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section heading="Emploi du temps">
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5">
                            <tr><th class="p-3">Jour</th><th class="p-3">Horaire</th><th class="p-3">Matière / activité</th><th class="p-3">Enseignant</th><th class="p-3">Publication</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @forelse($emplois as $emploi)
                                <tr class="hover:bg-primary-50/50 dark:hover:bg-white/5">
                                    <td class="p-3">{{ $emploi->jour_semaine ?? '—' }}</td>
                                    <td class="p-3">{{ $emploi->heure_debut ?? '—' }} – {{ $emploi->heure_fin ?? '—' }}</td>
                                    <td class="p-3">{{ $emploi->matiere_nom ?: (isset($emploi->libelle_activite) ? $emploi->libelle_activite : 'Activité générale') }}</td>
                                    <td class="p-3">{{ trim(($emploi->enseignant_prenom ?? '').' '.($emploi->enseignant_nom ?? '')) ?: '—' }}</td>
                                    <td class="p-3">{{ $emploi->statut ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-8 text-center text-gray-500">Aucun emploi du temps actif pour cette classe et cette année.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">Sélectionnez une classe pour consulter ses informations, ses matières et son emploi du temps.</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
