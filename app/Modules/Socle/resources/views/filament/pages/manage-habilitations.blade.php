<x-filament-panels::page>
    @php
        $roleStats = $this->getRoleStats();
        $modulesSummary = $this->getModulesSummary();
        $selectedModule = $this->getModuleSelectionneConfig();
        $operations = $this->getOperationsPourModuleSelectionne();
        $sousFonctionnalites = $this->getSousFonctionnalitesPourModuleSelectionne();
        $allRoles = $this->getRoles();
    @endphp

    <!-- Zone principale centrée (1200px - 1280px) -->
    <div class="w-full max-w-[1240px] mx-auto space-y-6">

        <!-- Bloc Habilitation du personnel (Grande surface propre et légère) -->
        <div class="bg-white border border-[#E2E8F0] rounded-[12px] p-5 shadow-xs space-y-5">
            <!-- Description d'orientation -->
            <div>
                <p class="text-[13px] text-slate-600 leading-relaxed">
                    Sélectionnez le profil d'habilitation du personnel pour ajuster les droits d'accès aux modules et définir les opérations autorisées.
                </p>
            </div>

            <!-- Zone HABILITATION ACTIVE (Fond bleu extrêmement léger) -->
            <div class="bg-[#F0F7FF] border border-[#DBEAFE] rounded-[10px] p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="text-[11px] font-bold tracking-wider uppercase text-[#1948bd]">
                        HABILITATION ACTIVE
                    </div>
                    <div class="text-xl sm:text-2xl font-bold text-[#07163f] mt-0.5">
                        {{ $roleSelectionne }}
                    </div>
                </div>

                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-xs font-semibold self-start sm:self-auto">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>{{ $roleStats['operations_actives'] }} / {{ $roleStats['total_operations'] }} opérations autorisées</span>
                </div>
            </div>

            <!-- 6. Profils disponibles (Boutons / chips modernes avec wrap automatique) -->
            <div class="space-y-2">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Profils disponibles
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    @foreach ($allRoles as $role)
                        @php
                            $isActive = ($roleSelectionne === $role);
                        @endphp
                        <button
                            type="button"
                            wire:click="selectionnerRole('{{ $role }}')"
                            @class([
                                'h-[36px] px-3.5 rounded-[8px] text-xs font-semibold inline-flex items-center transition-all cursor-pointer border',
                                'bg-[#EFF6FF] border-[#1948bd] text-[#07163f] shadow-xs' => $isActive,
                                'bg-[#F8FAFC] border-[#E2E8F0] text-slate-700 hover:bg-slate-100 hover:text-slate-900' => ! $isActive,
                            ])
                        >
                            @if ($isActive)
                                <span class="w-1.5 h-1.5 rounded-full bg-[#1948bd] mr-1.5"></span>
                            @endif
                            <span>{{ $role }}</span>
                        </button>
                    @endforeach
                </div>

                <!-- Sélecteur synchronisé pour accessibilité et tests -->
                <div class="sr-only">
                    <select wire:model.live="roleSelectionne">
                        @foreach ($allRoles as $role)
                            <option value="{{ $role }}">{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- 7. Grille Desktop 40 / 60 : Modules (Gauche) + Opérations (Droite) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

            <!-- 8. PANNEAU GAUCHE : MODULES DU SYSTÈME (~40%) -->
            <div class="lg:col-span-5 bg-white border border-[#E2E8F0] rounded-[12px] shadow-xs overflow-hidden flex flex-col">
                <!-- En-tête du panneau -->
                <div class="p-4 sm:px-5 border-b border-[#E2E8F0] flex items-center justify-between">
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-[#07163f]">
                            Modules du système
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Sélectionnez un module applicatif
                        </p>
                    </div>
                    <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">
                        10 modules
                    </span>
                </div>

                <!-- Liste compacte des 10 modules -->
                <div class="divide-y divide-[#EEF2F7]">
                    @foreach ($modulesSummary as $nom => $mod)
                        @php
                            $isSelected = $mod['is_selected'];
                        @endphp
                        <button
                            type="button"
                            wire:click="selectionnerModule('{{ $nom }}')"
                            @class([
                                'w-full min-h-[54px] px-4 py-2.5 flex items-center justify-between text-left transition-colors cursor-pointer',
                                'bg-[#EFF6FF] border-l-4 border-l-[#1948bd]' => $isSelected,
                                'bg-white hover:bg-slate-50/80 border-l-4 border-l-transparent' => ! $isSelected,
                            ])
                        >
                            <div class="flex items-center gap-3 min-w-0 pr-2">
                                <div @class([
                                    'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-sm',
                                    'bg-blue-100 text-[#1948bd]' => $isSelected,
                                    'bg-slate-100 text-slate-500' => ! $isSelected,
                                ])>
                                    <x-filament::icon :icon="$mod['icon']" class="w-4 h-4" />
                                </div>
                                <div class="min-w-0">
                                    <div @class([
                                        'text-[13px] font-semibold truncate',
                                        'text-[#07163f]' => $isSelected,
                                        'text-slate-800' => ! $isSelected,
                                    ])>
                                        {{ $mod['nom'] }}
                                    </div>
                                    <div class="text-[12px] text-slate-500 truncate leading-tight">
                                        {{ $mod['description'] }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="inline-flex items-center text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                                    {{ $mod['operations_actives'] }}/{{ $mod['total_operations'] }} autorisées
                                </span>
                                <x-filament::icon
                                    icon="heroicon-m-chevron-right"
                                    @class([
                                        'w-4 h-4',
                                        'text-[#1948bd]' => $isSelected,
                                        'text-slate-400' => ! $isSelected,
                                    ])
                                />
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- 9. PANNEAU DROIT : OPÉRATIONS DU MODULE (~60%) -->
            <div class="lg:col-span-7 bg-white border border-[#E2E8F0] rounded-[12px] shadow-xs overflow-hidden flex flex-col">
                <!-- En-tête du panneau -->
                <div class="p-4 sm:px-5 border-b border-[#E2E8F0] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-[#07163f]">
                            Opérations — {{ $selectedModule['nom'] }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Habilitation : <span class="font-semibold text-slate-700">{{ $roleSelectionne }}</span>
                        </p>
                    </div>

                    <!-- Actions rapides sobres -->
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="toutAutoriserPourModule"
                            class="h-[34px] px-3.5 rounded-lg bg-[#1948bd] hover:bg-blue-700 text-white font-semibold text-xs inline-flex items-center gap-1.5 transition-colors cursor-pointer border border-[#1948bd]"
                        >
                            <x-filament::icon icon="heroicon-m-check" class="w-3.5 h-3.5" />
                            <span>Tout autoriser</span>
                        </button>
                        <button
                            type="button"
                            wire:click="demanderToutRestreindre"
                            class="h-[34px] px-3.5 rounded-lg bg-white border border-[#E2E8F0] hover:bg-slate-50 text-slate-700 font-semibold text-xs inline-flex items-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <x-filament::icon icon="heroicon-m-lock-closed" class="w-3.5 h-3.5 text-slate-400" />
                            <span>Tout restreindre</span>
                        </button>
                    </div>
                </div>

                <!-- Liste des 4 opérations fondamentales -->
                <div class="divide-y divide-[#EEF2F7]">
                    @foreach ($operations as $op)
                        @php
                            $isActif = $op['actif'];
                        @endphp
                        <div class="min-h-[62px] px-4 py-3 flex items-center justify-between gap-4">
                            <div class="flex items-start gap-3 min-w-0">
                                <div @class([
                                    'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5',
                                    'bg-blue-50 text-[#1948bd]' => $op['key'] === 'consulter',
                                    'bg-indigo-50 text-indigo-600' => $op['key'] === 'modifier',
                                    'bg-amber-50 text-amber-600' => $op['key'] === 'executer',
                                    'bg-emerald-50 text-emerald-600' => $op['key'] === 'imprimer',
                                ])>
                                    <x-filament::icon :icon="$op['icon']" class="w-4 h-4" />
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-semibold text-[#07163f]">
                                            {{ $op['nom'] }}
                                        </span>
                                        <code class="text-[11px] font-mono text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100">
                                            {{ $op['code'] }}
                                        </code>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5 leading-normal">
                                        {{ $op['description'] }}
                                    </p>
                                    @if ($op['dernier_motif'])
                                        <div class="mt-1.5 text-xs text-amber-800 bg-amber-50 px-2.5 py-1 rounded-md border border-amber-200/80 inline-flex items-center gap-1.5">
                                            <x-filament::icon icon="heroicon-m-information-circle" class="w-3.5 h-3.5 shrink-0 text-amber-600" />
                                            <span>Motif : {{ $op['dernier_motif'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-3 shrink-0">
                                @if ($isActif)
                                    <span class="inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        Autorisé
                                    </span>
                                @else
                                    <span class="inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                                        Non autorisé
                                    </span>
                                @endif

                                <!-- Switch toggle -->
                                <button
                                    type="button"
                                    wire:click="demanderChangement('{{ $op['code'] }}', {{ $isActif ? 'true' : 'false' }})"
                                    role="switch"
                                    aria-checked="{{ $isActif ? 'true' : 'false' }}"
                                    @class([
                                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-[#1948bd] focus:ring-offset-2',
                                        'bg-[#1948bd]' => $isActif,
                                        'bg-slate-300' => ! $isActif,
                                    ])
                                >
                                    <span
                                        @class([
                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-xs ring-0 transition duration-200 ease-in-out',
                                            'translate-x-5' => $isActif,
                                            'translate-x-0' => ! $isActif,
                                        ])
                                    ></span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Sous-fonctionnalités spécifiques optionnelles (Accordéon sobre) -->
                @if ($sousFonctionnalites->isNotEmpty())
                    <div class="border-t border-[#E2E8F0]">
                        <button
                            type="button"
                            wire:click="$toggle('afficherSousFonctionnalites')"
                            class="w-full flex items-center justify-between p-3.5 px-4 bg-slate-50/70 hover:bg-slate-100/70 transition-colors text-left cursor-pointer"
                        >
                            <span class="text-xs font-semibold text-slate-700">
                                Sous-fonctionnalités spécifiques du module ({{ $sousFonctionnalites->count() }})
                            </span>
                            <x-filament::icon
                                icon="heroicon-m-chevron-down"
                                class="w-4 h-4 text-slate-400 transition-transform {{ $afficherSousFonctionnalites ? 'rotate-180' : '' }}"
                            />
                        </button>

                        @if ($afficherSousFonctionnalites)
                            <div class="divide-y divide-[#EEF2F7] bg-white">
                                @foreach ($sousFonctionnalites as $sub)
                                    <div class="flex items-center justify-between p-3 px-4">
                                        <div class="min-w-0 pr-3">
                                            <div class="text-xs font-semibold text-slate-800 truncate">
                                                {{ $sub->nom }}
                                            </div>
                                            <div class="text-[11px] text-slate-500 truncate">
                                                {{ $sub->description ?? $sub->code }}
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3 shrink-0">
                                            <span @class([
                                                'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border',
                                                'bg-emerald-50 text-emerald-700 border-emerald-100' => $sub->habilitation_active,
                                                'bg-slate-100 text-slate-600 border-slate-200' => ! $sub->habilitation_active,
                                            ])>
                                                {{ $sub->habilitation_active ? 'Autorisé' : 'Restreint' }}
                                            </span>

                                            <input
                                                type="checkbox"
                                                wire:change="demanderChangement(@js($sub->code), @js($sub->habilitation_active))"
                                                @checked($sub->habilitation_active)
                                                class="h-4.5 w-4.5 rounded border-[#CBD5E1] text-[#1948bd] focus:ring-[#1948bd] cursor-pointer"
                                            />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- 11. Modale de Motif d'audit pour Restriction (Design institutionnel et moderne) -->
    <x-filament::modal id="motif-desactivation" width="lg">
        <x-slot name="heading">
            <span class="text-base font-semibold text-[#07163f]">
                Motif de restriction d'habilitation
            </span>
        </x-slot>

        <x-slot name="description">
            <span class="text-xs text-slate-500 leading-normal">
                Cette opération restreint immédiatement l'accès pour l'habilitation <strong class="text-slate-800">{{ $roleSelectionne }}</strong>. Un motif d'audit est obligatoire pour consigner la traçabilité dans le journal de sécurité.
            </span>
        </x-slot>

        <div class="space-y-1.5 mt-2">
            <label for="motif-habilitation" class="block text-[13px] font-semibold text-slate-800 mb-1.5">
                Motif de la restriction (min. 3 caractères)
            </label>
            <textarea
                id="motif-habilitation"
                wire:model="motif"
                rows="4"
                placeholder="Ex : Restriction temporaire décidée lors de la révision trimestrielle des accès..."
                class="block w-full rounded-[9px] border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#1948bd] focus:ring-1 focus:ring-[#1948bd] focus:outline-hidden"
            ></textarea>
            <p class="text-[12px] text-[#64748B] leading-relaxed">
                Ce motif sera archivé dans le journal d'audit de sécurité et consultable par les administrateurs.
            </p>
            @error('motif')
                <p class="text-xs text-rose-600 font-medium">{{ $message }}</p>
            @enderror
        </div>

        <x-slot name="footerActions">
            <div class="w-full flex items-center justify-between gap-3">
                <button
                    type="button"
                    x-on:click="$dispatch('close-modal', { id: 'motif-desactivation' })"
                    class="h-[40px] px-4 rounded-[8px] bg-white border border-[#CBD5E1] text-[#07163f] hover:bg-slate-50 font-semibold text-xs inline-flex items-center transition-colors cursor-pointer"
                >
                    Annuler
                </button>

                <button
                    type="button"
                    wire:click="confirmerDesactivation"
                    class="h-[40px] px-4 rounded-[8px] bg-[#1948bd] hover:bg-blue-700 text-white font-semibold text-xs inline-flex items-center gap-2 transition-colors cursor-pointer"
                >
                    <span wire:loading.remove wire:target="confirmerDesactivation">Confirmer la restriction</span>
                    <span wire:loading wire:target="confirmerDesactivation" class="inline-flex items-center gap-1.5">
                        <x-filament::loading-indicator class="h-3.5 w-3.5" /> Traitement...
                    </span>
                </button>
            </div>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
