<x-filament-panels::page>
    @php
        $userCourant = $this->userCourant;
        $matrice = $this->matrice;
        $users = $this->users;
        $currentCategorie = $this->moduleSelectionne ?? $matrice->keys()->first();
        
        $totalActives = 0;
        if ($userCourant) {
            $totalActives = $matrice->flatten()->where('habilitation_active', true)->count();
        }
    @endphp

    <!-- Zone principale centrée (1200px - 1280px) -->
    <div class="w-full max-w-[1240px] mx-auto space-y-6">

        <!-- Bloc Habilitations par utilisateur individuel -->
        <div class="bg-white border border-[#E2E8F0] rounded-[12px] p-5 shadow-xs space-y-5">
            <!-- Description d'orientation -->
            <div>
                <p class="text-[13px] text-slate-600 leading-relaxed">
                    Sélectionnez un utilisateur pour attribuer ou révoquer spécifiquement une habilitation dynamique. Les règles accordées ici prévalent sur ses rôles et groupes.
                </p>
            </div>

            <!-- Zone UTILISATEUR SÉLECTIONNÉ (Fond bleu très clair) -->
            <div class="bg-[#F0F7FF] border border-[#DBEAFE] rounded-[10px] p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="text-[11px] font-bold tracking-wider uppercase text-[#1948bd]">
                        UTILISATEUR SÉLECTIONNÉ
                    </div>
                    <div class="text-xl sm:text-2xl font-bold text-[#07163f] mt-0.5">
                        {{ $userCourant?->name ?? 'Aucun utilisateur sélectionné' }}
                    </div>
                    @if ($userCourant)
                        <div class="text-xs text-slate-500 mt-1 flex items-center gap-1.5 flex-wrap">
                            <span class="font-medium text-slate-700">{{ $userCourant->email }}</span>
                            <span>•</span>
                            <span class="px-2 py-0.5 rounded-md bg-blue-100/70 text-[#1948bd] font-semibold text-[11px]">
                                {{ implode(', ', $userCourant->getRoleNames()->toArray()) ?: 'Sans rôle spécifique' }}
                            </span>
                        </div>
                    @endif
                </div>

                @if ($userCourant)
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-xs font-semibold self-start sm:self-auto">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>{{ $totalActives }} habilitation(s) active(s)</span>
                    </div>
                @endif
            </div>

            <!-- Sélection de l'utilisateur avec recherche en direct -->
            <div class="space-y-1.5" x-data="{
                open: false,
                search: '',
                selectedId: @entangle('userSelectionne').live,
                users: {{ json_encode($users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => implode(', ', $u->getRoleNames()->toArray()) ?: 'Sans rôle spécifique',
                ])->values()->all()) }},
                get selectedUser() {
                    return this.users.find(u => u.id == this.selectedId) || null;
                },
                get filteredUsers() {
                    if (!this.search.trim()) return this.users;
                    const q = this.search.toLowerCase();
                    return this.users.filter(u => 
                        u.name.toLowerCase().includes(q) || 
                        u.email.toLowerCase().includes(q) ||
                        u.roles.toLowerCase().includes(q)
                    );
                },
                selectUser(id) {
                    this.selectedId = id;
                    this.open = false;
                    this.search = '';
                }
            }" @click.outside="open = false" @keydown.escape.window="open = false">
                <label for="userSelectSearchTrigger" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Compte utilisateur à administrer
                </label>

                @if ($users->isEmpty())
                    <p class="text-sm text-slate-500 italic">Aucun utilisateur disponible dans l'établissement courant.</p>
                @else
                    <!-- Synchronisation select invisible pour fallback accessibilité & tests -->
                    <select id="userSelect" wire:model.live="userSelectionne" class="sr-only">
                        @foreach ($users as $userItem)
                            <option value="{{ $userItem->id }}">{{ $userItem->name }} — {{ implode(', ', $userItem->getRoleNames()->toArray()) ?: 'Sans rôle' }} ({{ $userItem->email }})</option>
                        @endforeach
                    </select>

                    <div class="relative max-w-xl">
                        <!-- Sélecteur interactif stylisé avec affichage de l'utilisateur actif -->
                        <button
                            type="button"
                            id="userSelectSearchTrigger"
                            @click="open = !open; if(open) { $nextTick(() => $refs.searchInput.focus()); }"
                            class="w-full h-[46px] px-3.5 bg-white border border-[#CBD5E1] rounded-[9px] flex items-center justify-between text-left shadow-xs hover:border-[#1948bd] focus:outline-hidden focus:ring-2 focus:ring-[#1948bd]/20 transition-all cursor-pointer"
                            :class="{ 'border-[#1948bd] ring-2 ring-[#1948bd]/20': open }"
                        >
                            <div class="flex items-center gap-2.5 min-w-0 pr-2">
                                <template x-if="selectedUser">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-6 h-6 rounded-full bg-blue-100 text-[#1948bd] flex items-center justify-center text-[11px] font-bold shrink-0" x-text="selectedUser.name.charAt(0)"></div>
                                        <span class="text-sm font-semibold text-[#07163f] truncate" x-text="selectedUser.name"></span>
                                        <span class="text-xs text-slate-400 truncate" x-text="'— ' + selectedUser.roles + ' (' + selectedUser.email + ')'"></span>
                                    </div>
                                </template>
                                <template x-if="!selectedUser">
                                    <span class="text-sm text-slate-400">Rechercher ou sélectionner un compte utilisateur...</span>
                                </template>
                            </div>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" ::class="{ 'rotate-180': open }" />
                        </button>

                        <!-- Menu déroulant avec champ de recherche en direct -->
                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 translate-y-1"
                            class="absolute z-50 mt-1.5 w-full bg-white border border-[#CBD5E1] rounded-[10px] shadow-lg overflow-hidden"
                            style="display: none;"
                        >
                            <!-- Champ de recherche textuelle avec loupe -->
                            <div class="p-2.5 border-b border-[#E2E8F0] bg-slate-50/70 flex items-center gap-2">
                                <x-filament::icon icon="heroicon-m-magnifying-glass" class="w-4 h-4 text-slate-400 shrink-0" />
                                <input
                                    x-ref="searchInput"
                                    type="text"
                                    x-model="search"
                                    placeholder="Saisissez un nom, email ou rôle pour rechercher..."
                                    class="w-full bg-transparent border-0 p-0 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-hidden focus:ring-0"
                                    @keydown.enter.prevent="if(filteredUsers.length > 0) selectUser(filteredUsers[0].id)"
                                />
                                <template x-if="search.length > 0">
                                    <button type="button" @click="search = ''; $refs.searchInput.focus()" class="text-slate-400 hover:text-slate-600">
                                        <x-filament::icon icon="heroicon-m-x-mark" class="w-3.5 h-3.5" />
                                    </button>
                                </template>
                            </div>

                            <!-- Liste des utilisateurs filtrés -->
                            <div class="max-h-60 overflow-y-auto divide-y divide-slate-100">
                                <template x-for="u in filteredUsers" :key="u.id">
                                    <button
                                        type="button"
                                        @click="selectUser(u.id)"
                                        class="w-full px-3.5 py-2.5 flex items-center justify-between text-left hover:bg-[#F0F7FF] transition-colors cursor-pointer group"
                                        :class="{ 'bg-blue-50/80': u.id == selectedId }"
                                    >
                                        <div class="min-w-0 pr-2">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-xs font-semibold text-[#07163f] group-hover:text-[#1948bd]" x-text="u.name"></span>
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600" x-text="u.roles"></span>
                                            </div>
                                            <div class="text-[11px] text-slate-400 mt-0.5" x-text="u.email"></div>
                                        </div>
                                        <template x-if="u.id == selectedId">
                                            <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-[#1948bd] shrink-0" />
                                        </template>
                                    </button>
                                </template>

                                <template x-if="filteredUsers.length === 0">
                                    <div class="p-4 text-center text-xs text-slate-400">
                                        Aucun utilisateur ne correspond à votre recherche.
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if ($userCourant && $matrice->isNotEmpty())
            <!-- 3. Grille Desktop 40 / 60 : Modules (Gauche) + Fonctionnalités (Droite) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

                <!-- PANNEAU GAUCHE : MODULES DU SYSTÈME (~40%) -->
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
                            {{ $matrice->count() }} modules
                        </span>
                    </div>

                    <!-- Liste compacte des modules/catégories -->
                    <div class="divide-y divide-[#EEF2F7]">
                        @foreach ($matrice as $categorie => $fonctionnalites)
                            @php
                                $isSelected = ($currentCategorie === $categorie);
                                $activeCount = $fonctionnalites->where('habilitation_active', true)->count();
                                $totalCount = $fonctionnalites->count();
                            @endphp
                            <button
                                type="button"
                                wire:click="selectionnerModule('{{ $categorie }}')"
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
                                        <x-filament::icon icon="heroicon-o-cube" class="w-4 h-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <div @class([
                                            'text-[13px] font-semibold truncate',
                                            'text-[#07163f]' => $isSelected,
                                            'text-slate-800' => ! $isSelected,
                                        ])>
                                            {{ $categorie }}
                                        </div>
                                        <div class="text-[12px] text-slate-500 truncate leading-tight">
                                            Habilitations spécifiques à l'utilisateur
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="inline-flex items-center text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        {{ $activeCount }} / {{ $totalCount }} actives
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

                <!-- PANNEAU DROIT : DÉTAIL DES FONCTIONNALITÉS (~60%) -->
                <div class="lg:col-span-7 bg-white border border-[#E2E8F0] rounded-[12px] shadow-xs overflow-hidden flex flex-col">
                    <!-- En-tête du panneau -->
                    <div class="p-4 sm:px-5 border-b border-[#E2E8F0] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-[#07163f]">
                                Habilitations de l'utilisateur — Module {{ $currentCategorie }}
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Utilisateur : <span class="font-semibold text-slate-700">{{ $userCourant->name }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Liste des fonctionnalités de la catégorie -->
                    <div class="divide-y divide-[#EEF2F7]">
                        @if (isset($matrice[$currentCategorie]))
                            @foreach ($matrice[$currentCategorie] as $fonctionnalite)
                                @php
                                    $isActif = $fonctionnalite->habilitation_active;
                                @endphp
                                <div class="min-h-[62px] px-4 py-3 flex items-center justify-between gap-4">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <div @class([
                                            'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5',
                                            'bg-blue-50 text-[#1948bd]' => $isActif,
                                            'bg-slate-100 text-slate-400' => ! $isActif,
                                        ])>
                                            <x-filament::icon icon="heroicon-o-shield-check" class="w-4 h-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-sm font-semibold text-[#07163f]">
                                                    {{ $fonctionnalite->nom }}
                                                </span>
                                                <code class="text-[11px] font-mono text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100">
                                                    {{ $fonctionnalite->code }}
                                                </code>
                                                @if ($fonctionnalite->est_specifique_user)
                                                    <span class="px-2 py-0.5 text-[10px] font-semibold bg-purple-50 text-purple-700 rounded-full border border-purple-200/70">
                                                        Attribution spécifique
                                                    </span>
                                                @endif
                                            </div>
                                            @if ($fonctionnalite->description && $fonctionnalite->description !== $fonctionnalite->code)
                                                <p class="text-xs text-slate-500 mt-0.5 leading-normal">
                                                    {{ $fonctionnalite->description }}
                                                </p>
                                            @endif
                                            @if ($fonctionnalite->dernier_motif)
                                                <div class="mt-1.5 text-xs text-amber-800 bg-amber-50 px-2.5 py-1 rounded-md border border-amber-200/80 inline-flex items-center gap-1.5">
                                                    <x-filament::icon icon="heroicon-m-information-circle" class="w-3.5 h-3.5 shrink-0 text-amber-600" />
                                                    <span>Motif : {{ $fonctionnalite->dernier_motif }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 shrink-0">
                                        @if ($isActif)
                                            <span class="inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                                                Activée
                                            </span>
                                        @else
                                            <span class="inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                                                Désactivée
                                            </span>
                                        @endif

                                        <!-- Switch toggle -->
                                        <button
                                            type="button"
                                            wire:click="demanderChangement('{{ $fonctionnalite->code }}', {{ $isActif ? 'true' : 'false' }})"
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
                        @else
                            <div class="p-6 text-center text-xs text-slate-400">
                                Aucune fonctionnalité disponible dans ce module.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white border border-[#E2E8F0] rounded-[12px] p-8 text-center text-slate-500">
                <x-filament::icon icon="heroicon-o-user" class="w-12 h-12 mx-auto text-slate-300 mb-3" />
                <p class="text-sm font-semibold text-slate-700">Aucun utilisateur sélectionné</p>
                <p class="text-xs text-slate-400 mt-1">Sélectionnez un compte utilisateur ci-dessus pour administrer ses habilitations personnalisées.</p>
            </div>
        @endif
    </div>

    <!-- MODALE DU SOCLE VISUEL : MOTIF OBLIGATOIRE DE RETRAIT D'HABILITATION UTILISATEUR -->
    <x-filament::modal id="motif-retrait-user" width="lg">
        <x-slot name="heading">
            <span class="text-base font-semibold text-[#07163f]">
                Motif de retrait d'habilitation utilisateur
            </span>
        </x-slot>

        <x-slot name="description">
            <span class="text-xs text-slate-500 leading-normal">
                Cette opération révoque l'accès à cette fonctionnalité pour cet utilisateur spécifique. Un motif d'audit est obligatoire pour la traçabilité.
            </span>
        </x-slot>

        <div class="space-y-1.5 mt-2">
            <label for="motif-user" class="block text-[13px] font-semibold text-slate-800 mb-1.5">
                Motif de désactivation (min. 3 caractères)
            </label>
            <textarea
                id="motif-user"
                wire:model="motif"
                rows="4"
                placeholder="Ex : Restriction individuelle suite à un changement de poste ou de responsabilité..."
                class="block w-full rounded-[9px] border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#1948bd] focus:ring-1 focus:ring-[#1948bd] focus:outline-hidden"
            ></textarea>
            @error('motif')
                <p class="text-xs text-rose-600 font-medium">{{ $message }}</p>
            @enderror
        </div>

        <x-slot name="footerActions">
            <div class="w-full flex items-center justify-between gap-3">
                <button
                    type="button"
                    x-on:click="$dispatch('close-modal', { id: 'motif-retrait-user' })"
                    class="h-[40px] px-4 rounded-[8px] bg-white border border-[#CBD5E1] text-[#07163f] hover:bg-slate-50 font-semibold text-xs inline-flex items-center transition-colors cursor-pointer"
                >
                    Annuler
                </button>

                <button
                    type="button"
                    wire:click="confirmerRetrait"
                    class="h-[40px] px-4 rounded-[8px] bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs inline-flex items-center gap-2 transition-colors cursor-pointer"
                >
                    <span wire:loading.remove wire:target="confirmerRetrait">Confirmer le retrait</span>
                    <span wire:loading wire:target="confirmerRetrait" class="inline-flex items-center gap-1.5">
                        <x-filament::loading-indicator class="h-3.5 w-3.5" /> Traitement...
                    </span>
                </button>
            </div>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
