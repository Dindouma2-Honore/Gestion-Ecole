<x-filament-panels::page>
    @include('socle::filament.pages.partials.habilitation-tabs', ['active' => 'roles'])

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Rôle du personnel</x-slot>
            <x-slot name="description">Les droits techniques définis par Spatie restent obligatoires. Cette matrice ajoute un contrôle d'habilitation dynamique au niveau de l'établissement.</x-slot>

            <select wire:model.live="roleSelectionne" class="fi-input block w-full max-w-md rounded-xl border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                @foreach ($this->getRoles() as $role)
                    <option value="{{ $role }}">{{ $role }}</option>
                @endforeach
            </select>
        </x-filament::section>

        <!-- Module Cards Grid -->
        <div class="amb-card-grid">
            @foreach ($this->matrice as $categorie => $fonctionnalites)
                @php
                    $activeCount = $fonctionnalites->where('habilitation_active', true)->count();
                    $totalCount = $fonctionnalites->count();
                    $isSelected = $this->moduleSelectionne === $categorie;
                @endphp

                <button type="button" wire:click="selectionnerModule('{{ $categorie }}')" class="amb-card-button">
                    <x-ambassadors-card
                        icon="heroicon-o-shield-check"
                        label="Module"
                        :title="$categorie"
                        :badge="$activeCount.' / '.$totalCount.' actifs'"
                        :selected="$isSelected"
                        :action="$isSelected ? 'Masquer les fonctionnalités' : 'Ajuster les fonctionnalités'"
                    >Habilitations du rôle {{ $roleSelectionne }}</x-ambassadors-card>
                </button>
            @endforeach
        </div>

        <!-- Granular Checkboxes Panel for Selected Module -->
        @if ($this->moduleSelectionne && isset($this->matrice[$this->moduleSelectionne]))
            <x-filament::section class="animate-in fade-in slide-in-from-top-3 duration-200">
                <x-slot name="heading">
                    Habilitations granulaires — Module {{ $this->moduleSelectionne }} ({{ $roleSelectionne }})
                </x-slot>
                <x-slot name="description">
                    Cochez pour activer ou décochez pour désactiver l'accès à une fonctionnalité spécifique. Une désactivation requiert un motif explicite d'audit.
                </x-slot>

                <div class="space-y-2.5 mt-3">
                    @foreach ($this->matrice[$this->moduleSelectionne] as $fonctionnalite)
                        <div class="flex items-center justify-between p-4 bg-slate-50/80 dark:bg-slate-900/90 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors">
                            <div class="flex items-start gap-3.5">
                                <label class="relative flex items-center cursor-pointer mt-0.5">
                                    <input
                                        type="checkbox"
                                        wire:change="demanderChangement(@js($fonctionnalite->code), @js($fonctionnalite->habilitation_active))"
                                        @checked($fonctionnalite->habilitation_active)
                                        class="fi-checkbox-input h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </label>
                                <div>
                                    <div class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                        <span>{{ $fonctionnalite->nom }}</span>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1.5 flex-wrap">
                                        @if ($fonctionnalite->description && $fonctionnalite->description !== $fonctionnalite->code)
                                            <span>{{ $fonctionnalite->description }}</span>
                                        @endif
                                        <code class="px-1.5 py-0.5 text-[10px] bg-slate-200/80 dark:bg-slate-800 rounded font-mono text-slate-700 dark:text-slate-300">{{ $fonctionnalite->code }}</code>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                @if ($fonctionnalite->dernier_motif)
                                    <span
                                        x-data
                                        x-tooltip.raw="Motif d'audit : {{ $fonctionnalite->dernier_motif }}"
                                        title="Motif d'audit : {{ $fonctionnalite->dernier_motif }}"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-amber-50 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/80 shadow-xs cursor-help max-w-[260px]"
                                    >
                                        <x-filament::icon icon="heroicon-m-information-circle" class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" />
                                        <span class="truncate font-sans text-[11px]">Motif: {{ $fonctionnalite->dernier_motif }}</span>
                                    </span>
                                @endif

                                <span @class([
                                    'inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold transition-all',
                                    'bg-emerald-600 text-white shadow-xs' => $fonctionnalite->habilitation_active,
                                    'bg-rose-600 text-white shadow-xs' => ! $fonctionnalite->habilitation_active,
                                ])>
                                    {{ $fonctionnalite->habilitation_active ? 'Activé' : 'Désactivé' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>

    <!-- Modal Motif Obligatoire pour Désactivation -->
    <x-filament::modal id="motif-desactivation" width="lg">
        <x-slot name="heading">Motif de désactivation d'habilitation</x-slot>
        <x-slot name="description">Cette opération bloque immédiatement l'accès à cette fonctionnalité pour le rôle {{ $roleSelectionne }}. Un motif d'audit est obligatoire.</x-slot>

        <div class="space-y-2">
            <label for="motif-habilitation" class="text-sm font-medium text-slate-900 dark:text-white">Motif de désactivation (min. 3 caractères)</label>
            <textarea id="motif-habilitation" wire:model="motif" rows="4" placeholder="Ex: Restriction temporaire de la fonctionnalité durant la clôture..." class="fi-input block w-full rounded-xl border-slate-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"></textarea>
            @error('motif') <p class="text-sm text-danger-600 font-medium">{{ $message }}</p> @enderror
        </div>

        <x-slot name="footerActions">
            <x-filament::button color="danger" wire:click="confirmerDesactivation">
                <span wire:loading.remove wire:target="confirmerDesactivation">Confirmer la désactivation</span>
                <span wire:loading wire:target="confirmerDesactivation" class="inline-flex items-center gap-2">
                    <x-message-loading size="18" class="text-white" /> Traitement en cours...
                </span>
            </x-filament::button>
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'motif-desactivation' })">
                Annuler
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
