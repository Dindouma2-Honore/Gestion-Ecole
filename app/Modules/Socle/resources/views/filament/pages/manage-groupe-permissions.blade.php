<x-filament-panels::page>
    @php
        $groupeCourant = $this->groupeCourant;
        $matrice = $this->matrice;
        $groupes = $this->groupes;
        $allUsers = $this->tousLesUtilisateurs;
        $currentCategorie = $this->moduleSelectionne ?? $matrice->keys()->first();
        
        $totalAccordees = 0;
        if ($groupeCourant) {
            $totalAccordees = $matrice->flatten()->where('habilitation_active', true)->count();
        }
    @endphp

    <!-- Zone principale centrée (1200px - 1280px) -->
    <div class="w-full max-w-[1240px] mx-auto space-y-6">

        <!-- Bloc Habilitations par groupe d'utilisateurs -->
        <div class="bg-white border border-[#E2E8F0] rounded-[12px] p-5 shadow-xs space-y-5">
            <!-- Description d'orientation -->
            <div>
                <p class="text-[13px] text-slate-600 leading-relaxed">
                    Sélectionnez un groupe pour administrer ses habilitations dynamiques par module et par fonctionnalité.
                </p>
            </div>

            <!-- Zone GROUPE ACTIF (Fond bleu très clair) -->
            <div class="bg-[#F0F7FF] border border-[#DBEAFE] rounded-[10px] p-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <div class="text-[11px] font-bold tracking-wider uppercase text-[#1948bd]">
                        GROUPE ACTIF
                    </div>
                    <div class="text-xl sm:text-2xl font-bold text-[#07163f] mt-0.5">
                        {{ $groupeCourant?->nom ?? 'Aucun groupe sélectionné' }}
                    </div>
                    @if ($groupeCourant?->description)
                        <p class="text-xs text-slate-500 mt-1">
                            {{ $groupeCourant->description }}
                        </p>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    @if ($groupeCourant)
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-50 border border-blue-200/80 text-[#1948bd] text-xs font-semibold">
                            <x-filament::icon icon="heroicon-m-user-group" class="w-3.5 h-3.5 text-[#1948bd]" />
                            <span>{{ $groupeCourant->membres()->count() }} membre(s)</span>
                        </div>

                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-xs font-semibold">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>{{ $totalAccordees }} accordée(s)</span>
                        </div>

                        <!-- Bouton Mettre à jour le groupe (Ouvre la modale du socle visuel) -->
                        <button
                            type="button"
                            x-on:click="$dispatch('open-modal', { id: 'modal-membres-groupe' })"
                            wire:click="ouvrirModalMembres"
                            class="h-[34px] px-3.5 rounded-lg bg-white border border-[#CBD5E1] text-[#07163f] hover:bg-slate-50 text-xs font-semibold inline-flex items-center gap-1.5 transition-colors cursor-pointer shadow-2xs"
                        >
                            <x-filament::icon icon="heroicon-o-users" class="w-3.5 h-3.5 text-[#1948bd]" />
                            <span>Mettre à jour le groupe</span>
                        </button>
                    @endif

                    <!-- Bouton Créer un groupe (Ouvre la modale du socle visuel) -->
                    <button
                        type="button"
                        x-on:click="$dispatch('open-modal', { id: 'modal-creer-groupe' })"
                        wire:click="ouvrirModalCreerGroupe"
                        class="h-[34px] px-3.5 rounded-lg bg-[#1948bd] hover:bg-blue-700 text-white text-xs font-semibold inline-flex items-center gap-1.5 transition-colors cursor-pointer border border-[#1948bd]"
                    >
                        <x-filament::icon icon="heroicon-o-plus" class="w-3.5 h-3.5" />
                        <span>Créer un groupe</span>
                    </button>
                </div>
            </div>

            <!-- Groupes disponibles (Boutons / chips modernes avec wrap automatique) -->
            <div class="space-y-2">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Groupes disponibles
                </div>

                @if ($groupes->isEmpty())
                    <p class="text-sm text-slate-500 italic">Aucun groupe disponible dans l'établissement courant.</p>
                @else
                    <div class="flex flex-wrap gap-2 pt-1">
                        @foreach ($groupes as $groupe)
                            @php
                                $isActive = ($groupeSelectionne === $groupe->id);
                            @endphp
                            <button
                                type="button"
                                wire:click="selectionnerGroupe({{ $groupe->id }})"
                                @class([
                                    'h-[36px] px-3.5 rounded-[8px] text-xs font-semibold inline-flex items-center transition-all cursor-pointer border',
                                    'bg-[#EFF6FF] border-[#1948bd] text-[#07163f] shadow-xs' => $isActive,
                                    'bg-[#F8FAFC] border-[#E2E8F0] text-slate-700 hover:bg-slate-100 hover:text-slate-900' => ! $isActive,
                                ])
                            >
                                @if ($isActive)
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#1948bd] mr-1.5"></span>
                                @endif
                                <span>{{ $groupe->nom }}</span>
                                <span class="ml-1.5 text-[11px] text-slate-400 font-normal">({{ $groupe->membres()->count() }})</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <!-- Sélecteur synchronisé pour accessibilité et tests -->
                <div class="sr-only">
                    <select wire:model.live="groupeSelectionne">
                        @foreach ($groupes as $groupe)
                            <option value="{{ $groupe->id }}">{{ $groupe->nom }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if ($groupeCourant && $matrice->isNotEmpty())
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
                                $catInfo = $this->getCategorieInfo($categorie);
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
                                        <x-filament::icon :icon="$catInfo['icon']" class="w-4 h-4" />
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
                                            {{ $catInfo['description'] }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="inline-flex items-center text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        {{ $activeCount }} / {{ $totalCount }} accordées
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
                    <!-- En-tête du panneau (Mention explicite attendue par les tests : Détail des fonctionnalités — Module {Nom}) -->
                    <div class="p-4 sm:px-5 border-b border-[#E2E8F0] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-[#07163f]">
                                Détail des fonctionnalités — Module {{ $currentCategorie }}
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Groupe : <span class="font-semibold text-slate-700">{{ $groupeCourant->nom }}</span>
                            </p>
                        </div>

                        <!-- Actions rapides sobres comme dans Habilitation du personnel -->
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="toutAccorderPourModule"
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
                                                Accordée
                                            </span>
                                        @else
                                            <span class="inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                                                Non accordée
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
                <x-filament::icon icon="heroicon-o-user-group" class="w-12 h-12 mx-auto text-slate-300 mb-3" />
                <p class="text-sm font-semibold text-slate-700">Aucun groupe sélectionné</p>
                <p class="text-xs text-slate-400 mt-1">Créez un groupe ou sélectionnez un groupe existant ci-dessus pour administrer ses habilitations.</p>
            </div>
        @endif
    </div>

    <!-- MODALE DU SOCLE VISUEL : CRÉER UN GROUPE -->
    <x-filament::modal id="modal-creer-groupe" width="lg">
        <x-slot name="heading">
            <span class="text-base font-semibold text-[#07163f]">
                Créer un nouveau groupe
            </span>
        </x-slot>

        <x-slot name="description">
            <span class="text-xs text-slate-500 leading-normal">
                Définissez le nom du groupe et ses membres initiaux. Les permissions accordées à ce groupe s'appliqueront à l'ensemble de ses utilisateurs.
            </span>
        </x-slot>

        <div class="space-y-4 mt-2">
            <div>
                <label for="nouveauGroupeNom" class="block text-[13px] font-semibold text-slate-800 mb-1.5">
                    Nom du groupe <span class="text-rose-500">*</span>
                </label>
                <input
                    type="text"
                    id="nouveauGroupeNom"
                    wire:model="nouveauGroupeNom"
                    placeholder="Ex : Comité pédagogique, Référents RH, Caisse centrale..."
                    class="block w-full h-[44px] rounded-[9px] border border-[#CBD5E1] bg-white px-3.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#1948bd] focus:ring-1 focus:ring-[#1948bd] focus:outline-hidden"
                />
                @error('nouveauGroupeNom')
                    <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="nouveauGroupeDescription" class="block text-[13px] font-semibold text-slate-800 mb-1.5">
                    Description du groupe (optionnelle)
                </label>
                <textarea
                    id="nouveauGroupeDescription"
                    wire:model="nouveauGroupeDescription"
                    rows="3"
                    placeholder="Précisez la finalité ou les attributions spécifiques de ce groupe..."
                    class="block w-full rounded-[9px] border border-[#CBD5E1] bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#1948bd] focus:ring-1 focus:ring-[#1948bd] focus:outline-hidden"
                ></textarea>
                @error('nouveauGroupeDescription')
                    <p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-[13px] font-semibold text-slate-800 mb-1.5">
                    Membres initiaux (optionnel)
                </label>
                <div class="max-h-48 overflow-y-auto rounded-[9px] border border-[#CBD5E1] p-2 space-y-1 bg-slate-50/50">
                    @foreach ($allUsers as $u)
                        <label class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-white transition-colors cursor-pointer text-xs">
                            <input
                                type="checkbox"
                                value="{{ $u->id }}"
                                wire:model="nouveauxMembresIds"
                                class="h-4 w-4 rounded border-[#CBD5E1] text-[#1948bd] focus:ring-[#1948bd]"
                            />
                            <span class="font-medium text-slate-800">{{ $u->name }}</span>
                            <span class="text-slate-400 text-[11px] font-mono">({{ $u->email }})</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <x-slot name="footerActions">
            <div class="w-full flex items-center justify-between gap-3">
                <button
                    type="button"
                    x-on:click="$dispatch('close-modal', { id: 'modal-creer-groupe' })"
                    class="h-[40px] px-4 rounded-[8px] bg-white border border-[#CBD5E1] text-[#07163f] hover:bg-slate-50 font-semibold text-xs inline-flex items-center transition-colors cursor-pointer"
                >
                    Annuler
                </button>

                <button
                    type="button"
                    wire:click="creerGroupe"
                    class="h-[40px] px-5 rounded-[8px] bg-[#1948bd] hover:bg-blue-700 text-white font-semibold text-xs inline-flex items-center gap-2 transition-colors cursor-pointer"
                >
                    <span wire:loading.remove wire:target="creerGroupe">Créer le groupe</span>
                    <span wire:loading wire:target="creerGroupe" class="inline-flex items-center gap-1.5">
                        <x-filament::loading-indicator class="h-3.5 w-3.5" /> Création...
                    </span>
                </button>
            </div>
        </x-slot>
    </x-filament::modal>

    <!-- MODALE DU SOCLE VISUEL : GÉRER LES MEMBRES D'UN GROUPE -->
    <x-filament::modal id="modal-membres-groupe" width="lg">
        <x-slot name="heading">
            <span class="text-base font-semibold text-[#07163f]">
                Mettre à jour le groupe — {{ $groupeCourant?->nom }}
            </span>
        </x-slot>

        <x-slot name="description">
            <span class="text-xs text-slate-500 leading-normal">
                Ajoutez ou retirez des utilisateurs de ce groupe. Les modifications sont prises en compte immédiatement et enregistrées dans le journal d'audit.
            </span>
        </x-slot>

        <div class="space-y-4 mt-2">
            <!-- Ajouter un utilisateur -->
            <div class="rounded-[10px] border border-[#DBEAFE] bg-[#F0F7FF] p-3.5 space-y-2">
                <div class="text-xs font-bold text-[#1948bd] uppercase tracking-wider">
                    Ajouter un utilisateur au groupe
                </div>
                <div class="flex items-center gap-2">
                    <select
                        wire:model="userAAjouterId"
                        class="block flex-1 h-[42px] rounded-[8px] border border-[#CBD5E1] bg-white px-3 text-xs text-slate-900 focus:border-[#1948bd] focus:ring-1 focus:ring-[#1948bd] focus:outline-hidden"
                    >
                        <option value="">Sélectionner un utilisateur...</option>
                        @php
                            $membreIds = $groupeCourant ? $groupeCourant->membres()->pluck('users.id')->all() : [];
                        @endphp
                        @foreach ($allUsers->whereNotIn('id', $membreIds) as $userOpt)
                            <option value="{{ $userOpt->id }}">{{ $userOpt->name }} ({{ $userOpt->email }})</option>
                        @endforeach
                    </select>

                    <button
                        type="button"
                        wire:click="ajouterMembreAuGroupe"
                        class="h-[42px] px-4 rounded-[8px] bg-[#1948bd] hover:bg-blue-700 text-white font-semibold text-xs inline-flex items-center gap-1.5 transition-colors cursor-pointer shrink-0"
                    >
                        <x-filament::icon icon="heroicon-m-plus" class="w-4 h-4" />
                        <span>Ajouter</span>
                    </button>
                </div>
            </div>

            <!-- Liste des membres actuels -->
            <div>
                <div class="text-xs font-semibold text-slate-700 mb-2 flex items-center justify-between">
                    <span>Membres actuels du groupe</span>
                    <span class="text-slate-400 font-normal">({{ $groupeCourant?->membres()->count() ?? 0 }})</span>
                </div>

                <div class="max-h-60 overflow-y-auto rounded-[9px] border border-[#E2E8F0] divide-y divide-[#EEF2F7]">
                    @if ($groupeCourant && $groupeCourant->membres()->exists())
                        @foreach ($groupeCourant->membres as $membre)
                            <div class="p-3 flex items-center justify-between hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-2.5 min-w-0 pr-2">
                                    <div class="w-7 h-7 rounded-full bg-blue-100 text-[#1948bd] flex items-center justify-center text-xs font-bold shrink-0">
                                        {{ mb_substr($membre->name, 0, 1) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold text-slate-800 truncate">
                                            {{ $membre->name }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 truncate">
                                            {{ $membre->email }}
                                        </div>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    wire:click="retirerMembreDuGroupe({{ $membre->id }})"
                                    class="h-[30px] px-2.5 rounded-md bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-semibold inline-flex items-center gap-1 transition-colors cursor-pointer border border-rose-200/60 shrink-0"
                                    title="Retirer ce membre du groupe"
                                >
                                    <x-filament::icon icon="heroicon-m-trash" class="w-3.5 h-3.5" />
                                    <span>Retirer</span>
                                </button>
                            </div>
                        @endforeach
                    @else
                        <div class="p-6 text-center text-xs text-slate-400">
                            Aucun membre dans ce groupe pour le moment.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Modifier les informations du groupe -->
            <div x-data="{ ouvert: false }" class="rounded-[10px] border border-[#E2E8F0] overflow-hidden">
                <button
                    type="button"
                    x-on:click="ouvert = !ouvert"
                    class="w-full p-3 bg-slate-50 flex items-center justify-between text-left text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                >
                    <span class="flex items-center gap-1.5">
                        <x-filament::icon icon="heroicon-o-pencil-square" class="w-3.5 h-3.5 text-[#1948bd]" />
                        <span>Modifier le nom ou la description du groupe</span>
                    </span>
                    <x-filament::icon icon="heroicon-m-chevron-down" class="w-3.5 h-3.5 text-slate-400 transition-transform" ::class="{ 'rotate-180': ouvert }" />
                </button>
                <div x-show="ouvert" class="p-3.5 space-y-3 bg-white border-t border-[#E2E8F0]">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nom du groupe</label>
                        <input
                            type="text"
                            wire:model="groupeNom"
                            class="block w-full h-[38px] rounded-[8px] border border-[#CBD5E1] bg-white px-3 text-xs text-slate-900 focus:border-[#1948bd] focus:ring-1 focus:ring-[#1948bd] focus:outline-hidden"
                        />
                        @error('groupeNom')
                            <p class="text-[11px] text-rose-600 font-medium mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Description</label>
                        <textarea
                            wire:model="groupeDescription"
                            rows="2"
                            class="block w-full rounded-[8px] border border-[#CBD5E1] bg-white px-3 py-2 text-xs text-slate-900 focus:border-[#1948bd] focus:ring-1 focus:ring-[#1948bd] focus:outline-hidden"
                        ></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button
                            type="button"
                            wire:click="mettreAJourGroupeInfos"
                            class="h-[34px] px-3.5 rounded-lg bg-[#1948bd] hover:bg-blue-700 text-white font-semibold text-xs inline-flex items-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <x-filament::icon icon="heroicon-m-check" class="w-3.5 h-3.5" />
                            <span>Enregistrer les informations</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <x-slot name="footerActions">
            <div class="w-full flex justify-end">
                <button
                    type="button"
                    x-on:click="$dispatch('close-modal', { id: 'modal-membres-groupe' })"
                    class="h-[40px] px-5 rounded-[8px] bg-white border border-[#CBD5E1] text-[#07163f] hover:bg-slate-50 font-semibold text-xs inline-flex items-center transition-colors cursor-pointer"
                >
                    Fermer
                </button>
            </div>
        </x-slot>
    </x-filament::modal>

    <!-- MODALE DU SOCLE VISUEL : MOTIF OBLIGATOIRE DE RETRAIT D'HABILITATION -->
    <x-filament::modal id="motif-retrait-groupe" width="lg">
        <x-slot name="heading">
            <span class="text-base font-semibold text-[#07163f]">
                Motif obligatoire de retrait
            </span>
        </x-slot>

        <x-slot name="description">
            <span class="text-xs text-slate-500 leading-normal">
                Cette opération retire immédiatement la permission accordée au groupe sélectionné. Un motif est exigé pour la traçabilité en journal d'audit.
            </span>
        </x-slot>

        <div class="space-y-1.5 mt-2">
            <label for="motif-groupe" class="block text-[13px] font-semibold text-slate-800 mb-1.5">
                Motif de retrait (min. 3 caractères)
            </label>
            <textarea
                id="motif-groupe"
                wire:model="motif"
                rows="4"
                placeholder="Ex : Retrait de l'accès suite au changement d'organisation..."
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
                    x-on:click="$dispatch('close-modal', { id: 'motif-retrait-groupe' })"
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
