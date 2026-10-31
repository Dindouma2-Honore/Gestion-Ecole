@php
    $statut = (string) $programme->statut;
    $statutConfig = match ($statut) {
        'analyse_en_attente' => [
            'label' => 'En attente d\'analyse',
            'badge' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 ring-1 ring-inset ring-gray-600/20',
            'icon' => 'heroicon-m-clock',
        ],
        'analyse_en_cours' => [
            'label' => 'Analyse en cours...',
            'badge' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 ring-1 ring-inset ring-blue-600/20',
            'icon' => 'heroicon-m-arrow-path',
        ],
        'a_valider' => [
            'label' => 'À valider par l\'enseignant',
            'badge' => 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 ring-1 ring-inset ring-amber-600/20',
            'icon' => 'heroicon-m-exclamation-triangle',
        ],
        'soumis' => [
            'label' => 'Soumis (en attente Direction)',
            'badge' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 ring-1 ring-inset ring-indigo-600/20',
            'icon' => 'heroicon-m-paper-airplane',
        ],
        'valide', 'publie' => [
            'label' => 'Validé & Actif',
            'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 ring-1 ring-inset ring-emerald-600/20',
            'icon' => 'heroicon-m-check-badge',
        ],
        'echec_analyse' => [
            'label' => 'Échec de l\'analyse',
            'badge' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 ring-1 ring-inset ring-rose-600/20',
            'icon' => 'heroicon-m-x-circle',
        ],
        'rejete' => [
            'label' => 'Rejeté',
            'badge' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 ring-1 ring-inset ring-rose-600/20',
            'icon' => 'heroicon-m-x-mark',
        ],
        default => [
            'label' => ucfirst($statut),
            'badge' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 ring-1 ring-inset ring-gray-600/20',
            'icon' => 'heroicon-m-document-text',
        ],
    };

    $chapitres = $programme->chapitres ?? collect();
    $totalChapitres = $chapitres->count();
    $totalLecons = $chapitres->sum(fn ($c) => $c->lecons ? $c->lecons->count() : 0);
    $totalHeures = $chapitres->sum(fn ($c) => $c->lecons ? $c->lecons->sum('volume_horaire_prevu') : 0)
        ?: $chapitres->sum('volume_horaire_prevu');

    $metadata = is_array($programme->metadata_analyse) ? $programme->metadata_analyse : [];
    $derniereAnalyse = $programme->derniereAnalyse;
    $aiResult = ($derniereAnalyse && is_array($derniereAnalyse->analysis_result)) ? $derniereAnalyse->analysis_result : [];

    $nomClasse = 'Non assignée';
    if ($programme->classe_id) {
        $nomClasse = \Illuminate\Support\Facades\DB::table('classes')->where('id', $programme->classe_id)->value('nom') ?? "Classe #{$programme->classe_id}";
    }

    $nomNiveau = '—';
    if ($programme->niveau_id) {
        $nomNiveau = \Illuminate\Support\Facades\DB::table('niveaux')->where('id', $programme->niveau_id)->value('nom') ?? "Niveau #{$programme->niveau_id}";
    }

    $nomSalle = 'Non assignée';
    if ($programme->salle_id) {
        $nomSalle = \Illuminate\Support\Facades\DB::table('salles')->where('id', $programme->salle_id)->value('nom') ?? "Salle #{$programme->salle_id}";
    }

    $nomEnseignant = 'Non spécifié';
    if ($programme->enseignant_id) {
        $nomEnseignant = \Illuminate\Support\Facades\DB::table('enseignants')
            ->join('employes', 'employes.id', '=', 'enseignants.employe_id')
            ->where('enseignants.id', $programme->enseignant_id)
            ->selectRaw("TRIM(CONCAT(COALESCE(employes.prenom, ''), ' ', COALESCE(employes.nom, ''))) as nom_complet")
            ->value('nom_complet')
            ?: \Illuminate\Support\Facades\DB::table('users')->where('id', $programme->enseignant_id)->value('name')
            ?: "Enseignant #{$programme->enseignant_id}";
    }
@endphp

<div class="space-y-6 text-gray-900 dark:text-gray-100">

    {{-- Bannière d'état spécifique --}}
    @if($statut === 'a_valider')
        <div class="rounded-xl border border-amber-300 bg-amber-50/80 p-4 text-amber-900 shadow-sm dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 text-amber-600 dark:text-amber-400 mt-0.5">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-bold">Extraction terminée — Vérification requise</h3>
                    <p class="mt-1 text-xs leading-relaxed text-amber-800 dark:text-amber-300">
                        Le moteur d'analyse a extrait <strong>{{ $totalChapitres }} chapitres</strong> et <strong>{{ $totalLecons }} notions/leçons</strong> depuis votre document.
                        Veuillez inspecter la structure ci-dessous. Vous pouvez ensuite cliquer sur <strong>« Valider la structure extraite »</strong> au bas de cette fenêtre pour transmettre le programme à la direction.
                    </p>
                </div>
            </div>
        </div>
    @elseif($statut === 'echec_analyse')
        <div class="rounded-xl border border-rose-300 bg-rose-50/80 p-4 text-rose-900 shadow-sm dark:border-rose-700/60 dark:bg-rose-950/40 dark:text-rose-200">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 text-rose-600 dark:text-rose-400 mt-0.5">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-bold">Échec de l'extraction automatique du document</h3>
                    <p class="mt-1 text-xs leading-relaxed text-rose-800 dark:text-rose-300">
                        {{ $programme->erreur_analyse ?: 'Le format du document n’a pas permis d’extraire automatiquement les chapitres et leçons.' }}
                    </p>
                </div>
            </div>
        </div>
    @elseif($statut === 'analyse_en_cours')
        <div class="rounded-xl border border-blue-300 bg-blue-50/80 p-4 text-blue-900 shadow-sm dark:border-blue-700/60 dark:bg-blue-950/40 dark:text-blue-200">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 text-blue-600 dark:text-blue-400 mt-0.5 animate-spin">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-bold">Extraction en cours...</h3>
                    <p class="mt-1 text-xs text-blue-800 dark:text-blue-300">
                        Le document est actuellement analysé par le système. Veuillez rafraîchir dans quelques instants.
                    </p>
                </div>
            </div>
        </div>
    @elseif($statut === 'analyse_en_attente')
        <div class="rounded-xl border border-amber-300 bg-amber-50/80 p-4 text-amber-900 shadow-sm dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 text-amber-600 dark:text-amber-400 mt-0.5">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-bold">Document en attente d'analyse intelligente</h3>
                    <p class="mt-1 text-xs leading-relaxed text-amber-800 dark:text-amber-300">
                        Ce programme a un fichier source en attente d'analyse. Cliquez sur <strong>« Lancer l’analyse IA »</strong> en bas à droite pour extraire automatiquement les modules, notions et objectifs.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Cartes de synthèse (KPIs) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
        <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-3.5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Matière</span>
            <p class="mt-1 text-sm font-bold text-gray-900 dark:text-white truncate" title="{{ $programme->matiere?->nom }}">
                {{ $programme->matiere?->nom ?? '—' }}
            </p>
            <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $programme->matiere?->code }}</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-3.5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Classe & Niveau</span>
            <p class="mt-1 text-sm font-bold text-gray-900 dark:text-white truncate">
                {{ $nomClasse }}
            </p>
            <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $nomNiveau }}</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-3.5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Structure extraite</span>
            <p class="mt-1 text-sm font-bold text-gray-900 dark:text-white">
                {{ $totalChapitres }} <span class="text-xs font-normal text-gray-500">chapitres</span>
            </p>
            <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $totalLecons }} notions / leçons</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-3.5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Volume horaire</span>
            <p class="mt-1 text-sm font-bold text-indigo-600 dark:text-indigo-400">
                {{ $totalHeures ? $totalHeures . ' h' : '—' }}
            </p>
            <span class="text-[11px] text-gray-500 dark:text-gray-400">Total prévisionnel</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-3.5 shadow-sm dark:border-white/10 dark:bg-white/5 col-span-2 md:col-span-4 lg:col-span-1">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Statut du programme</span>
            <div class="mt-1">
                <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold {{ $statutConfig['badge'] }}">
                    {{ $statutConfig['label'] }}
                </span>
            </div>
            <span class="mt-1 block text-[11px] text-gray-500 dark:text-gray-400">Version {{ $programme->version ?? 1 }}</span>
        </div>
    </div>

    {{-- Métadonnées de l'analyse automatique (si disponibles) --}}
    @if(!empty($metadata))
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3 dark:border-white/10">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </span>
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Rapport de l'analyse automatique</span>
                </div>
                @if(!empty($metadata['date_analyse']))
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Analysé le {{ \Carbon\Carbon::parse($metadata['date_analyse'])->translatedFormat('d F Y à H:i') }}
                    </span>
                @endif
            </div>

            <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Fichier source :</span>
                    <span class="ml-1 font-semibold text-gray-900 dark:text-white">{{ $metadata['fichier'] ?? 'Document importé' }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Chapitres détectés :</span>
                    <span class="ml-1 font-semibold text-emerald-600 dark:text-emerald-400">{{ $metadata['chapitres_extraits'] ?? $totalChapitres }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Leçons & notions identifiées :</span>
                    <span class="ml-1 font-semibold text-emerald-600 dark:text-emerald-400">{{ $metadata['lecons_extraites'] ?? $totalLecons }}</span>
                </div>
            </div>
        </div>
    @endif

    {{-- Rapport d'analyse approfondie IA (Objectifs, Compétences & Contrôle Qualité) --}}
    @if(!empty($aiResult))
        <div class="rounded-xl border border-primary-200 bg-gradient-to-br from-primary-50/40 via-white to-gray-50/50 p-5 shadow-sm dark:border-primary-900/40 dark:from-primary-950/20 dark:via-gray-900 dark:to-gray-900/60 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200/80 pb-3 dark:border-white/10">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                        </svg>
                    </span>
                    <div>
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            Analyse Intelligente IA
                            @if($derniereAnalyse?->ai_provider)
                                <span class="rounded-full bg-primary-100 dark:bg-primary-950 px-2 py-0.5 text-[10px] font-semibold text-primary-700 dark:text-primary-300">
                                    {{ ucfirst($derniereAnalyse->ai_provider) }} ({{ $derniereAnalyse->ai_model }})
                                </span>
                            @endif
                        </h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Synthèse sémantique et contrôle qualité du document pédagogique</p>
                    </div>
                </div>

                @if(!empty($aiResult['duration']))
                    <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        ⏱️ Durée estimée : {{ $aiResult['duration'] }}
                    </span>
                @endif
            </div>

            {{-- Résumé / Description --}}
            @if(!empty($aiResult['summary']) || !empty($aiResult['description']))
                <div class="text-xs leading-relaxed text-gray-700 dark:text-gray-300 bg-white/70 dark:bg-white/5 p-3 rounded-lg border border-gray-100 dark:border-white/5">
                    <strong>Synthèse globale :</strong>
                    {{ $aiResult['summary'] ?? $aiResult['description'] }}
                </div>
            @endif

            {{-- 3 Colonnes : Objectifs, Compétences, Prérequis --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                {{-- Objectifs pédagogiques --}}
                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                    <span class="font-bold text-gray-900 dark:text-white flex items-center gap-1.5 mb-2">
                        🎯 Objectifs pédagogiques
                    </span>
                    @if(!empty($aiResult['objectifs']) && is_array($aiResult['objectifs']))
                        <ul class="space-y-1 list-disc list-inside text-gray-600 dark:text-gray-400">
                            @foreach($aiResult['objectifs'] as $obj)
                                <li>{{ $obj }}</li>
                            @endforeach
                        </ul>
                    @else
                        <span class="text-gray-400 italic">Non spécifiés</span>
                    @endif
                </div>

                {{-- Compétences visées --}}
                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                    <span class="font-bold text-gray-900 dark:text-white flex items-center gap-1.5 mb-2">
                        🔑 Compétences visées
                    </span>
                    @if(!empty($aiResult['skills']) && is_array($aiResult['skills']))
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($aiResult['skills'] as $skill)
                                <span class="rounded bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                    {{ $skill }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <span class="text-gray-400 italic">Non spécifiées</span>
                    @endif
                </div>

                {{-- Prérequis --}}
                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                    <span class="font-bold text-gray-900 dark:text-white flex items-center gap-1.5 mb-2">
                        📋 Prérequis recommandés
                    </span>
                    @if(!empty($aiResult['prerequisites']) && is_array($aiResult['prerequisites']))
                        <ul class="space-y-1 list-disc list-inside text-gray-600 dark:text-gray-400">
                            @foreach($aiResult['prerequisites'] as $prereq)
                                <li>{{ $prereq }}</li>
                            @endforeach
                        </ul>
                    @else
                        <span class="text-gray-400 italic">Aucun prérequis particulier</span>
                    @endif
                </div>
            </div>

            {{-- Contrôle qualité documentaire (Incohérences, Informations manquantes, Doublons) --}}
            @php
                $hasInconsistencies = !empty($aiResult['inconsistencies']) && is_array($aiResult['inconsistencies']);
                $hasMissingInfo = !empty($aiResult['missing_information']) && is_array($aiResult['missing_information']);
                $hasDuplicates = !empty($aiResult['duplicates']) && is_array($aiResult['duplicates']);
                $hasQualityAlerts = $hasInconsistencies || $hasMissingInfo || $hasDuplicates;
            @endphp

            @if($hasQualityAlerts)
                <div class="rounded-lg border border-amber-200 bg-amber-50/50 p-3.5 dark:border-amber-900/40 dark:bg-amber-950/20 text-xs space-y-2">
                    <span class="font-bold text-amber-900 dark:text-amber-200 flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        Alertes Qualité & Cohérence Documentaire
                    </span>

                    @if($hasInconsistencies)
                        <div>
                            <span class="font-semibold text-rose-700 dark:text-rose-400">Points d'incohérence relevés :</span>
                            <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 mt-0.5">
                                @foreach($aiResult['inconsistencies'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($hasMissingInfo)
                        <div>
                            <span class="font-semibold text-amber-800 dark:text-amber-300">Informations manquantes conseillées :</span>
                            <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 mt-0.5">
                                @foreach($aiResult['missing_information'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($hasDuplicates)
                        <div>
                            <span class="font-semibold text-indigo-700 dark:text-indigo-400">Doublons éventuels détectés :</span>
                            <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 mt-0.5">
                                @foreach($aiResult['duplicates'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @else
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-2.5 text-xs text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300 flex items-center gap-2">
                    <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span>Contrôle qualité validé : structure cohérente, aucun doublon ni anomalie majeure détectée dans le document.</span>
                </div>
            @endif
        </div>
    @endif

    {{-- Arborescence détaillée du programme (Chapitres & Leçons) --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                Structure Pédagogique Détaillée ({{ $totalChapitres }} chapitres)
            </h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                Total : {{ $totalHeures }} heures planifiées
            </span>
        </div>

        @if($chapitres->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/50 p-8 text-center dark:border-white/10 dark:bg-white/5">
                <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Aucun chapitre enregistré</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Le document n'a pas encore été analysé ou n'a fourni aucune structure.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($chapitres as $indexChapitre => $chapitre)
                    @php
                        $lecons = $chapitre->lecons ?? collect();
                        $heuresChapitre = $chapitre->volume_horaire_prevu ?: $lecons->sum('volume_horaire_prevu');
                        $typeStructure = ucfirst($chapitre->type_structure ?: 'Chapitre');
                    @endphp

                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                        {{-- En-tête de chapitre --}}
                        <div class="flex flex-wrap items-center justify-between gap-3 bg-gray-50/80 px-4 py-3 border-b border-gray-200 dark:bg-white/5 dark:border-white/10">
                            <div class="flex items-center gap-2.5">
                                <span class="inline-flex items-center rounded-md bg-primary-100 px-2.5 py-1 text-xs font-bold text-primary-800 dark:bg-primary-950/70 dark:text-primary-300">
                                    {{ $typeStructure }} {{ $chapitre->ordre ?? ($indexChapitre + 1) }}
                                </span>
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white">
                                    {{ $chapitre->titre }}
                                </h4>
                            </div>

                            <div class="flex items-center gap-3 text-xs">
                                @if($heuresChapitre)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-0.5 font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        {{ $heuresChapitre }} h
                                    </span>
                                @endif
                                <span class="text-gray-500 dark:text-gray-400 font-medium">
                                    {{ $lecons->count() }} notion(s)
                                </span>
                            </div>
                        </div>

                        {{-- Objectifs ou description du chapitre --}}
                        @if(!empty($chapitre->objectif) || !empty($chapitre->objectifs_pedagogiques) || !empty($chapitre->contenu))
                            <div class="px-4 py-2.5 bg-blue-50/40 border-b border-blue-100/60 text-xs dark:bg-blue-950/20 dark:border-white/5 space-y-1">
                                @if(!empty($chapitre->objectif) || !empty($chapitre->objectifs_pedagogiques))
                                    <p class="text-blue-900 dark:text-blue-200">
                                        <strong class="font-semibold">🎯 Objectif :</strong>
                                        {{ $chapitre->objectif ?? $chapitre->objectifs_pedagogiques }}
                                    </p>
                                @endif
                                @if(!empty($chapitre->contenu))
                                    <p class="text-gray-600 dark:text-gray-400">
                                        {{ $chapitre->contenu }}
                                    </p>
                                @endif
                            </div>
                        @endif

                        {{-- Tableau des leçons du chapitre --}}
                        @if($lecons->isEmpty())
                            <div class="px-4 py-3 text-xs text-gray-500 italic dark:text-gray-400">
                                Aucune notion détaillée spécifiée pour ce chapitre.
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-gray-50/50 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                        <tr>
                                            <th class="px-4 py-2 w-12 text-center">#</th>
                                            <th class="px-4 py-2">Notion / Leçon</th>
                                            <th class="px-4 py-2 w-28">Type</th>
                                            <th class="px-4 py-2 w-24 text-center">Volume</th>
                                            <th class="px-4 py-2">Compétences & Objectifs visés</th>
                                            <th class="px-4 py-2 w-28 text-center">Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                        @foreach($lecons as $indexLecon => $lecon)
                                            @php
                                                $statutLecon = $lecon->statut ?? 'a_faire';
                                                $statutLeconBadge = match ($statutLecon) {
                                                    'terminee' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 ring-emerald-600/20',
                                                    'en_cours' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 ring-blue-600/20',
                                                    default => 'bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400 ring-gray-500/20',
                                                };
                                                $typeElement = ucfirst($lecon->type_element ?: 'Leçon');
                                            @endphp
                                            <tr class="hover:bg-gray-50/60 dark:hover:bg-white/5 transition-colors">
                                                <td class="px-4 py-2.5 text-center font-bold text-gray-400">
                                                    {{ $lecon->ordre ?? ($indexLecon + 1) }}
                                                </td>
                                                <td class="px-4 py-2.5 font-semibold text-gray-900 dark:text-white">
                                                    {{ $lecon->titre }}
                                                </td>
                                                <td class="px-4 py-2.5">
                                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                        {{ $typeElement }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2.5 text-center font-medium text-gray-700 dark:text-gray-300">
                                                    {{ $lecon->volume_horaire_prevu ? $lecon->volume_horaire_prevu . ' h' : '—' }}
                                                </td>
                                                <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">
                                                    {{ $lecon->objectifs ?: '—' }}
                                                </td>
                                                <td class="px-4 py-2.5 text-center">
                                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-medium ring-1 ring-inset {{ $statutLeconBadge }}">
                                                        {{ match($statutLecon) {
                                                            'terminee' => 'Terminée',
                                                            'en_cours' => 'En cours',
                                                            default => 'À faire',
                                                        } }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
