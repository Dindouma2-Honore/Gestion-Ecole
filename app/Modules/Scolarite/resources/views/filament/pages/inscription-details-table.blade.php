@php
    $argent = static fn ($montant): string => number_format((float) ($montant ?? 0), 0, ',', ' ').' FCFA';
    $date = static fn ($valeur): string => $valeur ? \Illuminate\Support\Carbon::parse($valeur)->format('d/m/Y') : '—';

    $statutInscription = (string) $record->statut;
    $badgeStatut = match ($statutInscription) {
        'active', 'validee' => ['label' => '🟢 Active', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-300'],
        'en_attente_versement' => ['label' => '🟡 En attente de versement', 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border-amber-300'],
        'en_attente_annulation' => ['label' => '🟠 En attente de validation (Fondateur)', 'class' => 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300 border-orange-300'],
        'annulee' => ['label' => '🔴 Annulée', 'class' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border-rose-300'],
        default => ['label' => ucfirst($statutInscription), 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 border-gray-300'],
    };

    $parent = $record->parent_principal;
    $eleve = $record->eleve;

    $dossier = $dossier ?? app(\App\Modules\Scolarite\Contracts\InscriptionFacturationPort::class)->getDossierFinancierComplet((int) $record->id);
    $totaux = $dossier['totaux'] ?? [
        'total_annuel' => 0,
        'total_paye' => 0,
        'reste_global' => (float) ($reste ?? 0),
        'est_solde' => false,
    ];
    $inscriptionData = $dossier['inscription'] ?? [
        'label' => "Frais d'inscription",
        'montant' => (float) config('scolarite.frais_inscription', 15_000),
        'paye' => 0,
        'reste' => (float) config('scolarite.frais_inscription', 15_000),
        'statut' => 'non_paye',
    ];
    $diversData = $dossier['frais_divers'] ?? [
        'items' => [],
        'total_exige' => 0,
        'total_paye' => 0,
        'reste' => 0,
    ];
    $scolariteData = $dossier['scolarite'] ?? [
        'montant_total' => 0,
        'total_paye' => 0,
        'reste_a_payer' => 0,
        'tranches' => [],
    ];
    $versementsList = $versements ?? ($dossier['versements'] ?? []);
@endphp

<div class="space-y-6">

    {{-- BANDEAU D'ÉTAT ANNULATION : CONTRÔLE À 4 YEUX --}}
    @if($record->statut === 'en_attente_annulation')
        <div class="rounded-xl border border-orange-300 bg-orange-50/90 p-5 shadow-sm dark:border-orange-800 dark:bg-orange-950/40">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-orange-100 text-orange-700 dark:bg-orange-900/60 dark:text-orange-300">
                    <x-filament::icon icon="heroicon-o-shield-exclamation" class="h-6 w-6" />
                </span>
                <div class="space-y-3 flex-1">
                    <div>
                        <p class="text-base font-bold text-orange-950 dark:text-orange-200">
                            Demande d’annulation d’inscription en cours d’examen (Contrôle à 4 yeux)
                        </p>
                        <p class="text-xs text-orange-900/80 dark:text-orange-300/80 mt-0.5">
                            Demandée @if($record->annulation_demandee_le) le <strong>{{ \Carbon\Carbon::parse($record->annulation_demandee_le)->format('d/m/Y à H:i') }}</strong> @endif
                            @if($record->demandeurAnnulation) par <strong>{{ $record->demandeurAnnulation->name }}</strong> @endif.
                            @if($record->motif_annulation) — <span class="italic">« {{ $record->motif_annulation }} »</span> @endif
                        </p>
                    </div>

                    {{-- WORKFLOW 4-YEUX PROGRESSION --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        {{-- Œil 1 : Visa Comptable --}}
                        <div class="rounded-lg border p-3 {{ $record->aVisaComptable() ? 'border-emerald-300 bg-emerald-50/90 dark:border-emerald-800 dark:bg-emerald-950/40' : 'border-amber-300 bg-amber-50/90 dark:border-amber-800 dark:bg-amber-950/40' }}">
                            <div class="flex items-center gap-2">
                                <span class="text-sm">{{ $record->aVisaComptable() ? '🟢' : '🟡' }}</span>
                                <span class="font-bold text-xs uppercase tracking-wider {{ $record->aVisaComptable() ? 'text-emerald-950 dark:text-emerald-200' : 'text-amber-950 dark:text-amber-200' }}">
                                    1er œil : Visa Financier (Comptabilité)
                                </span>
                            </div>
                            @if($record->aVisaComptable())
                                <p class="text-xs text-emerald-900 dark:text-emerald-300 mt-1">
                                    Apposé le <strong>{{ \Carbon\Carbon::parse($record->annulation_visee_le)->format('d/m/Y H:i') }}</strong>
                                    @if($record->viseurAnnulation) par <strong>{{ $record->viseurAnnulation->name }}</strong> @endif
                                </p>
                                @if($record->visa_comptable_notes)
                                    <p class="text-[11px] text-emerald-800/90 dark:text-emerald-300/90 mt-0.5 italic">
                                        « {{ $record->visa_comptable_notes }} »
                                    </p>
                                @endif
                            @else
                                <p class="text-xs text-amber-900 dark:text-amber-300 mt-1">
                                    En attente de vérification financière et du visa de la Comptabilité.
                                </p>
                            @endif
                        </div>

                        {{-- Œil 2 : Validation Fondateur --}}
                        <div class="rounded-lg border p-3 {{ $record->aVisaComptable() ? 'border-orange-300 bg-orange-100/70 dark:border-orange-700 dark:bg-orange-950/60' : 'border-gray-200 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/60 opacity-75' }}">
                            <div class="flex items-center gap-2">
                                <span class="text-sm">{{ $record->aVisaComptable() ? '🟠' : '⚪' }}</span>
                                <span class="font-bold text-xs uppercase tracking-wider {{ $record->aVisaComptable() ? 'text-orange-950 dark:text-orange-200' : 'text-gray-600 dark:text-gray-400' }}">
                                    2e œil : Décision finale (Fondateur)
                                </span>
                            </div>
                            <p class="text-xs mt-1 {{ $record->aVisaComptable() ? 'text-orange-900 dark:text-orange-300 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                @if($record->aVisaComptable())
                                    Prêt pour la validation définitive et la libération de la place par le Fondateur.
                                @else
                                    Bloqué en attente du visa comptable préalable (séparation des pouvoirs).
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @elseif($record->statut === 'annulee')
        <div class="rounded-xl border border-rose-300 bg-rose-50/90 p-5 shadow-sm dark:border-rose-800 dark:bg-rose-950/40">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300">
                    <x-filament::icon icon="heroicon-o-no-symbol" class="h-6 w-6" />
                </span>
                <div class="space-y-2 flex-1">
                    <p class="text-base font-bold text-rose-950 dark:text-rose-200">
                        Inscription officiellement annulée (Contrôle à 4 yeux complété)
                    </p>
                    <p class="text-xs text-rose-900/80 dark:text-rose-300/80">
                        La place a été libérée dans la classe et le dossier scolaire est désactivé. L’élève ne figure plus parmi les effectifs inscrits.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div class="rounded-lg border border-rose-200 bg-white/70 p-2.5 text-xs text-rose-900 dark:border-rose-900/40 dark:bg-gray-900/70 dark:text-rose-200">
                            <span class="font-bold">1. Visa Comptable :</span>
                            @if($record->annulation_visee_le)
                                le {{ \Carbon\Carbon::parse($record->annulation_visee_le)->format('d/m/Y H:i') }}
                            @endif
                            @if($record->viseurAnnulation)
                                par {{ $record->viseurAnnulation->name }}
                            @endif
                            @if($record->visa_comptable_notes)
                                <div class="mt-0.5 text-[11px] italic">« {{ $record->visa_comptable_notes }} »</div>
                            @endif
                        </div>
                        <div class="rounded-lg border border-rose-200 bg-white/70 p-2.5 text-xs text-rose-900 dark:border-rose-900/40 dark:bg-gray-900/70 dark:text-rose-200">
                            <span class="font-bold">2. Validation Fondateur :</span>
                            @if($record->annulation_validee_le)
                                le {{ \Carbon\Carbon::parse($record->annulation_validee_le)->format('d/m/Y H:i') }}
                            @endif
                            @if($record->validateurAnnulation)
                                par {{ $record->validateurAnnulation->name }} (Fondateur)
                            @endif
                            @if($record->motif_annulation)
                                <div class="mt-0.5 text-[11px] italic">Motif : {{ $record->motif_annulation }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- SECTION HISTORIQUE DES TRANSFERTS SI PRÉSENTS --}}
    @if($record->relationLoaded('transferts') ? $record->transferts->isNotEmpty() : $record->transferts()->exists())
        @php
            $transfertsList = $record->relationLoaded('transferts') ? $record->transferts : $record->transferts()->with(['classeOrigine', 'classeDestination', 'auteur'])->get();
        @endphp
        <div class="rounded-xl border border-sky-200 bg-sky-50/70 p-4 shadow-sm dark:border-sky-800 dark:bg-sky-950/30">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-sky-700 dark:text-sky-300">
                    <x-filament::icon icon="heroicon-o-arrows-right-left" class="h-5 w-5" />
                </span>
                <p class="text-sm font-bold text-sky-950 dark:text-sky-200">
                    Historique des transferts d'affectation de classe ({{ $transfertsList->count() }})
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-sky-200 text-sky-800 dark:border-sky-800 dark:text-sky-300">
                            <th class="py-1.5 px-2 font-semibold">Date</th>
                            <th class="py-1.5 px-2 font-semibold">De</th>
                            <th class="py-1.5 px-2 font-semibold">Vers</th>
                            <th class="py-1.5 px-2 font-semibold">Motif</th>
                            <th class="py-1.5 px-2 font-semibold text-right">Ancien solde</th>
                            <th class="py-1.5 px-2 font-semibold text-right">Nouveau solde</th>
                            <th class="py-1.5 px-2 font-semibold">Effectué par</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sky-100 dark:divide-sky-900/60">
                        @foreach($transfertsList as $tr)
                            <tr>
                                <td class="py-1.5 px-2 font-medium">{{ $date($tr->date_transfert) }}</td>
                                <td class="py-1.5 px-2">{{ $tr->classeOrigine?->nom ?? '—' }}</td>
                                <td class="py-1.5 px-2 font-bold text-primary-700 dark:text-primary-300">{{ $tr->classeDestination?->nom ?? '—' }}</td>
                                <td class="py-1.5 px-2 italic">{{ $tr->motif }}</td>
                                <td class="py-1.5 px-2 text-right">{{ $argent($tr->solde_avant_transfert) }}</td>
                                <td class="py-1.5 px-2 text-right font-bold text-primary-700 dark:text-primary-300">{{ $argent($tr->solde_apres_transfert) }}</td>
                                <td class="py-1.5 px-2 text-gray-500">{{ $tr->auteur?->name ?? 'Système' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- SECTION 1 : FICHE ÉLÈVE ET DOSSIER D'INSCRIPTION --}}
    <x-filament::section icon="heroicon-o-academic-cap">
        <x-slot name="heading">
            <div class="flex items-center justify-between gap-4">
                <span class="text-base font-bold text-gray-900 dark:text-white">Dossier d'inscription & Identité de l'élève</span>
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold {{ $badgeStatut['class'] }}">
                    {{ $badgeStatut['label'] }}
                </span>
            </div>
        </x-slot>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            {{-- Colonne 1 : Identité élève --}}
            <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-800 dark:bg-gray-900/60">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Élève</p>
                <p class="mt-1 text-lg font-black text-gray-900 dark:text-white">
                    {{ trim(($eleve?->nom ?? '').' '.($eleve?->prenom ?? '')) }}
                </p>
                <div class="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-300">
                    <p><span class="font-medium text-gray-500">Matricule :</span> <span class="font-mono font-bold">{{ $eleve?->matricule_permanent ?: 'Non attribué' }}</span></p>
                    <p><span class="font-medium text-gray-500">Sexe :</span> {{ ($eleve?->sexe === 'F' || $eleve?->sexe === 'f') ? 'Féminin' : 'Masculin' }}</p>
                    <p><span class="font-medium text-gray-500">Date de naissance :</span> {{ $date($eleve?->date_naissance) }}</p>
                    <p><span class="font-medium text-gray-500">Lieu de naissance :</span> {{ $eleve?->lieu_naissance ?: '—' }}</p>
                </div>
            </div>

            {{-- Colonne 2 : Scolarité & Classe --}}
            <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-800 dark:bg-gray-900/60">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Affectation scolaire</p>
                <p class="mt-1 text-lg font-black text-primary-700 dark:text-primary-400">
                    {{ $record->classe?->nom ?? 'Classe non définie' }}
                </p>
                <div class="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-300">
                    <p><span class="font-medium text-gray-500">Année scolaire :</span> <span class="font-semibold">{{ $record->anneeScolaire?->libelle ?? 'Active' }}</span></p>
                    <p><span class="font-medium text-gray-500">Date d'inscription :</span> {{ $date($record->date_inscription) }}</p>
                    <p><span class="font-medium text-gray-500">Type :</span> <span class="inline-flex items-center rounded-md bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/60 dark:text-blue-200">{{ ucfirst((string) $record->type) }}</span></p>
                    <p><span class="font-medium text-gray-500">Statut du dossier :</span> {{ ucfirst((string) $record->statut) }}</p>
                </div>
            </div>

            {{-- Colonne 3 : Parent / Tuteur légal --}}
            <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-800 dark:bg-gray-900/60">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Responsable payeur / Parent</p>
                <p class="mt-1 text-lg font-black text-gray-900 dark:text-white">
                    {{ $record->parent_principal_nom ?: 'Aucun parent renseigné' }}
                </p>
                <div class="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-300">
                    <p><span class="font-medium text-gray-500">Téléphone :</span> <span class="font-mono font-bold">{{ $record->parent_principal_telephone ?: ($parent?->telephone ?: 'Non renseigné') }}</span></p>
                    <p><span class="font-medium text-gray-500">Email :</span> {{ $parent?->email ?: ($eleve?->email ?: 'Non renseigné') }}</p>
                    <p><span class="font-medium text-gray-500">Lien de parenté :</span> {{ ucfirst((string) ($parent?->pivot?->lien ?? 'Tuteur')) }}</p>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- SECTION 2 : INDICATEURS FINANCIERS GLOBAUX (KPIs) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        {{-- Total Annuel Dû --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total annuel dû</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300">
                    <x-filament::icon icon="heroicon-o-calculator" class="h-5 w-5" />
                </span>
            </div>
            <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">
                {{ $argent($totaux['total_annuel']) }}
            </p>
            <p class="mt-1 text-[11px] text-gray-500">Inscription + Frais divers + Scolarité</p>
        </div>

        {{-- Total Déjà Encaissé --}}
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-xs dark:border-emerald-800/60 dark:bg-emerald-950/20">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">Total encaissé / réglé</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                </span>
            </div>
            <p class="mt-2 text-2xl font-black text-emerald-700 dark:text-emerald-400">
                {{ $argent($totaux['total_paye']) }}
            </p>
            <p class="mt-1 text-[11px] text-emerald-700/80 dark:text-emerald-400/80">Quittances validées par la caisse</p>
        </div>

        {{-- Solde Restant à Payer --}}
        <div class="rounded-xl border {{ $totaux['reste_global'] <= 0 ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-800 dark:bg-emerald-950/20' : 'border-amber-200 bg-amber-50/60 dark:border-amber-800 dark:bg-amber-950/30' }} p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide {{ $totaux['reste_global'] <= 0 ? 'text-emerald-800 dark:text-emerald-300' : 'text-amber-800 dark:text-amber-300' }}">
                    Solde restant à payer
                </span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $totaux['reste_global'] <= 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300' }}">
                    <x-filament::icon icon="heroicon-o-banknotes" class="h-5 w-5" />
                </span>
            </div>
            <p class="mt-2 text-2xl font-black {{ $totaux['reste_global'] <= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                {{ $argent($totaux['reste_global']) }}
            </p>
            <p class="mt-1 text-[11px] font-semibold {{ $totaux['reste_global'] <= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                {{ $totaux['reste_global'] <= 0 ? '🟢 Année scolaire entièrement soldée' : '⚠️ Montant restant à régulariser' }}
            </p>
        </div>
    </div>

    {{-- SECTION 3 : FRAIS D'INSCRIPTION ET FRAIS DIVERS SOUSCRITS --}}
    <x-filament::section icon="heroicon-o-tag" heading="Frais d'inscription et prestations annexes souscrites">
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 uppercase text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Prestation / Rubrique</th>
                        <th class="px-4 py-3 font-semibold">Nature</th>
                        <th class="px-4 py-3 text-right font-semibold">Montant Exigé</th>
                        <th class="px-4 py-3 text-right font-semibold">Montant Réglé</th>
                        <th class="px-4 py-3 text-right font-semibold">Solde Restant</th>
                        <th class="px-4 py-3 text-center font-semibold">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    {{-- Ligne Frais d'inscription --}}
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                        <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">
                            <span class="flex items-center gap-1.5">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary-100 text-[10px] font-bold text-primary-700 dark:bg-primary-900 dark:text-primary-300">1</span>
                                {{ $inscriptionData['label'] ?? "Frais d'inscription" }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">Obligatoire</span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                            {{ $argent($inscriptionData['montant']) }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">
                            {{ $argent($inscriptionData['paye']) }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold {{ $inscriptionData['reste'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500' }}">
                            {{ $argent($inscriptionData['reste']) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if(($inscriptionData['statut'] ?? '') === 'couvert')
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">🟢 Couvert</span>
                            @elseif(($inscriptionData['statut'] ?? '') === 'partiel')
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">🟡 Partiel</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-950 dark:text-rose-300">🔴 Non payé</span>
                            @endif
                        </td>
                    </tr>

                    {{-- Lignes Frais Divers --}}
                    @forelse($diversData['items'] as $item)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                            <td class="px-4 py-3 font-semibold text-gray-800 dark:text-gray-200">
                                <span class="flex items-center gap-1.5 pl-2">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-[10px] font-bold text-amber-700 dark:bg-amber-900 dark:text-amber-300">2</span>
                                    {{ $item['nom'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">Frais divers</span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                                {{ $argent($item['montant']) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ $argent($item['paye']) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold {{ $item['reste'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500' }}">
                                {{ $argent($item['reste']) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if(($item['statut'] ?? '') === 'couvert')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">🟢 Couvert</span>
                                @elseif(($item['statut'] ?? '') === 'partiel')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">🟡 Partiel</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-950 dark:text-rose-300">🔴 Non payé</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        @if(empty($diversData['items']))
                            <tr>
                                <td colspan="6" class="px-4 py-2.5 text-center text-xs text-gray-500 italic">
                                    Aucun frais divers facultatif souscrit pour cette inscription.
                                </td>
                            </tr>
                        @endif
                    @endforelse
                </tbody>
                <tfoot class="border-t-2 border-gray-200 bg-gray-50/80 font-bold dark:border-gray-700 dark:bg-gray-800/80">
                    <tr>
                        <td colspan="2" class="px-4 py-2.5 text-gray-900 dark:text-white">Sous-total Frais annexes (Inscription + Divers)</td>
                        <td class="px-4 py-2.5 text-right text-gray-900 dark:text-white">
                            {{ $argent(($inscriptionData['montant'] ?? 0) + ($diversData['total_exige'] ?? 0)) }}
                        </td>
                        <td class="px-4 py-2.5 text-right text-emerald-700 dark:text-emerald-400">
                            {{ $argent(($inscriptionData['paye'] ?? 0) + ($diversData['total_paye'] ?? 0)) }}
                        </td>
                        <td class="px-4 py-2.5 text-right text-amber-700 dark:text-amber-400">
                            {{ $argent(($inscriptionData['reste'] ?? 0) + ($diversData['reste'] ?? 0)) }}
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            @if((($inscriptionData['reste'] ?? 0) + ($diversData['reste'] ?? 0)) <= 0)
                                <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Soldé</span>
                            @else
                                <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300">En cours</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>

    {{-- SECTION 4 : ÉCHÉANCIER DE LA SCOLARITÉ ANNUELLE PAR TRANCHES --}}
    <x-filament::section icon="heroicon-o-calendar-days" heading="Échéancier de la scolarité annuelle (Tranches)">
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 uppercase text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Tranche</th>
                        <th class="px-4 py-3 font-semibold">Date d'échéance</th>
                        <th class="px-4 py-3 text-right font-semibold">Montant Exigé</th>
                        <th class="px-4 py-3 text-right font-semibold">Montant Payé</th>
                        <th class="px-4 py-3 text-right font-semibold">Solde Dû</th>
                        <th class="px-4 py-3 text-center font-semibold">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($scolariteData['tranches'] as $tranche)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                            <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">
                                <span class="flex items-center gap-1.5">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                                        {{ $tranche['ordre'] ?? $loop->iteration }}
                                    </span>
                                    {{ $tranche['libelle'] ?? ('Tranche '.($tranche['ordre'] ?? $loop->iteration)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $date($tranche['date_echeance'] ?? null) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                                {{ $argent($tranche['montant'] ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ $argent($tranche['paye'] ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold {{ ($tranche['reste'] ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500' }}">
                                {{ $argent($tranche['reste'] ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if(($tranche['statut'] ?? '') === 'payee')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">🟢 Soldée</span>
                                @elseif(($tranche['statut'] ?? '') === 'partiellement_payee')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">🟡 Partielle</span>
                                @elseif(($tranche['statut'] ?? '') === 'echue')
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-950 dark:text-rose-300">🔴 Échue</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">⚪ À venir</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-xs text-gray-500 italic">
                                Aucune tranche configurée pour cette classe ou configuration des frais en attente.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t-2 border-gray-200 bg-gray-50/80 font-bold dark:border-gray-700 dark:bg-gray-800/80">
                    <tr>
                        <td colspan="2" class="px-4 py-2.5 text-gray-900 dark:text-white">Total Frais de Scolarité</td>
                        <td class="px-4 py-2.5 text-right text-gray-900 dark:text-white">
                            {{ $argent($scolariteData['montant_total'] ?? 0) }}
                        </td>
                        <td class="px-4 py-2.5 text-right text-emerald-700 dark:text-emerald-400">
                            {{ $argent($scolariteData['total_paye'] ?? 0) }}
                        </td>
                        <td class="px-4 py-2.5 text-right text-amber-700 dark:text-amber-400">
                            {{ $argent($scolariteData['reste_a_payer'] ?? 0) }}
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            @if(($scolariteData['reste_a_payer'] ?? 0) <= 0 && ($scolariteData['montant_total'] ?? 0) > 0)
                                <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Soldée</span>
                            @else
                                <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300">En cours</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>

    {{-- SECTION 5 : QUITTANCES & PIÈCES JUSTIFICATIVES --}}
    <x-filament::section icon="heroicon-o-receipt-percent" heading="Règlements, Quittances et Pièces justificatives">
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 uppercase text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold">N° Reçu / Pièce</th>
                        <th class="px-4 py-3 text-right font-semibold">Montant Réglé</th>
                        <th class="px-4 py-3 font-semibold">Mode de règlement</th>
                        <th class="px-4 py-3 font-semibold">Statut</th>
                        <th class="px-4 py-3 text-right font-semibold">Actions / Impression</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($versementsList as $versement)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                            <td class="px-4 py-3 font-mono font-bold text-gray-900 dark:text-white">
                                {{ $versement['numero_recu'] ?: 'REC-En-cours' }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-black text-emerald-700 dark:text-emerald-400">
                                {{ $argent($versement['montant']) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                    {{ match($versement['mode'] ?? '') {
                                        'especes' => 'Espèces (Caisse)',
                                        'mobile_money' => 'Mobile Money',
                                        'bancaire' => 'Virement bancaire',
                                        default => ucfirst((string) ($versement['mode'] ?? '—')),
                                    } }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if(($versement['statut'] ?? '') === 'valide')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">🟢 Validé</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-medium text-rose-800 dark:bg-rose-950 dark:text-rose-300">🔴 Annulé</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('impressions.inscriptions.recu', ['inscription' => $record->id, 'paiement' => $versement['paiement_id']]) }}" target="_blank"
                                   class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300">
                                    <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-3.5 w-3.5" />
                                    Imprimer Reçu
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-xs text-gray-500 italic">
                                Aucun versement encaissé pour le moment.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

</div>
