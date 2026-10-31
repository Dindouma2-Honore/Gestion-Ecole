@php
    /** @var \App\Modules\Scolarite\Models\ParentTuteur $record */
    $parent = $record;
    $eleves = $parent->eleves;
    $totalEnfants = $eleves->count();

    $anneeCourante = null;
    $anneeCouranteId = null;
    $niveaux = [];
    try {
        $anneeCourante = app(\App\Modules\Socle\Contracts\AnneeScolaireServiceContract::class)->getAnneeCourante();
        $anneeCouranteId = $anneeCourante?->id;
        $niveaux = \Illuminate\Support\Facades\DB::table('niveaux')->pluck('nom', 'id')->all();
    } catch (\Throwable) {}
@endphp

<div class="space-y-6">
    {{-- Fiche d'identité et contact du parent --}}
    <x-filament::section
        heading="Identité & coordonnées du parent / tuteur"
        description="Informations personnelles et d'accès au portail de l'établissement"
        icon="heroicon-o-user-circle"
    >
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-white/10">
                {{-- Nom complet & statut --}}
                <div class="p-4 sm:p-5 flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <x-filament::icon icon="heroicon-o-users" class="h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Nom & Prénom</p>
                        <p class="mt-1 text-base font-bold text-gray-900 dark:text-white truncate">
                            {{ trim($parent->prenom . ' ' . $parent->nom) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                            {{ $parent->profession ?: 'Profession non renseignée' }}
                        </p>
                    </div>
                </div>

                {{-- Téléphone --}}
                <div class="p-4 sm:p-5 flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <x-filament::icon icon="heroicon-o-phone" class="h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Téléphone principal</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            @if($parent->telephone)
                                <a href="tel:{{ $parent->telephone }}" class="text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $parent->telephone }}
                                </a>
                            @else
                                <span class="text-gray-400 italic">Non renseigné</span>
                            @endif
                        </p>
                        <span class="inline-flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Contact direct
                        </span>
                    </div>
                </div>

                {{-- E-mail --}}
                <div class="p-4 sm:p-5 flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <x-filament::icon icon="heroicon-o-envelope" class="h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Adresse e-mail</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white truncate">
                            @if($parent->email)
                                <a href="mailto:{{ $parent->email }}" class="text-primary-600 hover:underline dark:text-primary-400 truncate">
                                    {{ $parent->email }}
                                </a>
                            @else
                                <span class="text-gray-400 italic">Non renseigné</span>
                            @endif
                        </p>
                        <span class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 block truncate">
                            {{ $parent->email ? 'Notifications & reçus' : 'Sans e-mail' }}
                        </span>
                    </div>
                </div>

                {{-- Portail & Synthèse --}}
                <div class="p-4 sm:p-5 flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400">
                        <x-filament::icon icon="heroicon-o-academic-cap" class="h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Portail & Fratrie</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            @if($parent->portail_actif)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                    Portail actif
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                    Portail inactif
                                </span>
                            @endif
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                {{ $totalEnfants }} {{ $totalEnfants > 1 ? 'enfants' : 'enfant' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- Liste détaillée de tous ses enfants dans l'établissement --}}
    <x-filament::section
        heading="Enfants scolarisés dans l'établissement"
        description="{{ $totalEnfants }} élève(s) rattaché(s) à ce parent/tuteur avec classe actuelle et responsabilités légales"
        icon="heroicon-o-academic-cap"
    >
        @if($totalEnfants === 0)
            <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center bg-gray-50/50 dark:bg-white/5">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400">
                    <x-filament::icon icon="heroicon-o-user-plus" class="h-6 w-6" />
                </div>
                <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">Aucun enfant rattaché</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                    Aucun élève n’est actuellement lié à ce parent ou tuteur. Le rattachement s’effectue automatiquement lors de l’admission ou de l’inscription d'un élève.
                </p>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10 shadow-sm">
                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-4 py-3.5">Élève</th>
                            <th scope="col" class="px-4 py-3.5">Matricule</th>
                            <th scope="col" class="px-4 py-3.5">Classe & Niveau</th>
                            <th scope="col" class="px-4 py-3.5">Statut</th>
                            <th scope="col" class="px-4 py-3.5">Lien & Rôles</th>
                            <th scope="col" class="px-4 py-3.5 text-right">Fiche</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10 bg-white dark:bg-gray-900">
                        @foreach($eleves as $eleve)
                            @php
                                $inscriptions = $eleve->inscriptions ?? collect();
                                $inscriptionCourante = ($anneeCouranteId ? $inscriptions->where('annee_scolaire_id', $anneeCouranteId)->whereNotIn('statut', ['annulee'])->first() : null)
                                    ?? $inscriptions->whereNotIn('statut', ['annulee'])->first()
                                    ?? $inscriptions->first();

                                $classeNom = $inscriptionCourante?->classe?->nom ?? $eleve->derniere_classe_frequente ?? 'Non affecté';
                                $niveauId = $inscriptionCourante?->classe?->niveau_id;
                                $niveauNom = $niveauId ? ($niveaux[$niveauId] ?? null) : null;
                                $anneeLibelle = $inscriptionCourante?->anneeScolaire?->libelle ?? null;

                                $statut = $inscriptionCourante?->statut ?? $eleve->statut;
                                $statutBadge = match ($statut) {
                                    'validee', 'active', 'inscrit' => ['label' => 'Inscrit', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'],
                                    'en_attente_versement' => ['label' => 'En attente versement', 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'],
                                    'en_attente_annulation' => ['label' => 'En attente annulation', 'class' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'],
                                    'en_cours' => ['label' => 'En cours', 'class' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'],
                                    'admis' => ['label' => 'Admis', 'class' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400'],
                                    'annulee' => ['label' => 'Annulée', 'class' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400'],
                                    default => ['label' => ucfirst((string) $statut), 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400'],
                                };

                                $lienRaw = strtolower((string) ($eleve->pivot->lien ?? ''));
                                $lienLabel = match ($lienRaw) {
                                    'pere' => 'Père',
                                    'mere' => 'Mère',
                                    'tuteur' => 'Tuteur',
                                    default => filled($eleve->pivot->lien ?? null) ? ucfirst($eleve->pivot->lien) : 'Parent / Tuteur',
                                };

                                $photoUrl = $eleve->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($eleve->photo) : null;
                                $initiales = mb_strtoupper(mb_substr($eleve->prenom, 0, 1) . mb_substr($eleve->nom, 0, 1));
                                $age = $eleve->date_naissance ? \Carbon\Carbon::parse($eleve->date_naissance)->age : null;

                                $ficheUrl = null;
                                try {
                                    if ($inscriptionCourante) {
                                        $ficheUrl = \App\Modules\Scolarite\Filament\Resources\InscriptionResource::getUrl('view', ['record' => $inscriptionCourante->id]);
                                    } else {
                                        $ficheUrl = \App\Modules\Scolarite\Filament\Resources\EleveResource::getUrl('edit', ['record' => $eleve->id]);
                                    }
                                } catch (\Throwable) {}
                            @endphp
                            <tr class="hover:bg-amber-50/40 dark:hover:bg-white/5 transition-colors">
                                {{-- Élève : Avatar + Nom + Prénom + Sexe & Âge --}}
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        @if($photoUrl)
                                            <img src="{{ $photoUrl }}" alt="{{ $eleve->nom }}" class="h-10 w-10 rounded-full object-cover ring-2 ring-gray-200 dark:ring-gray-700" />
                                        @else
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-amber-700 text-white font-bold text-xs ring-2 ring-gray-200 dark:ring-gray-700">
                                                {{ $initiales ?: 'EL' }}
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            @if($ficheUrl)
                                                <a href="{{ $ficheUrl }}" class="font-bold text-gray-950 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition-colors leading-tight inline-block">
                                                    {{ trim($eleve->nom . ' ' . $eleve->prenom) }}
                                                </a>
                                            @else
                                                <p class="font-bold text-gray-950 dark:text-white leading-tight">
                                                    {{ trim($eleve->nom . ' ' . $eleve->prenom) }}
                                                </p>
                                            @endif
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                {{ $eleve->sexe === 'F' ? 'Fille' : ($eleve->sexe === 'M' ? 'Garçon' : 'Non précisé') }}
                                                @if($age)
                                                    · {{ $age }} an{{ $age > 1 ? 's' : '' }}
                                                @elseif($eleve->date_naissance)
                                                    · Né(e) le {{ \Carbon\Carbon::parse($eleve->date_naissance)->format('d/m/Y') }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Matricule --}}
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-mono font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">
                                        {{ $eleve->matricule_permanent ?: '—' }}
                                    </span>
                                </td>

                                {{-- Classe & Niveau --}}
                                <td class="px-4 py-3.5">
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">
                                            {{ $classeNom }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $niveauNom ?: ($anneeLibelle ?: 'Scolarité en cours') }}
                                        </p>
                                    </div>
                                </td>

                                {{-- Statut scolaire --}}
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $statutBadge['class'] }}">
                                        {{ $statutBadge['label'] }}
                                    </span>
                                </td>

                                {{-- Lien & Responsabilités --}}
                                <td class="px-4 py-3.5">
                                    <div class="space-y-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                                            {{ $lienLabel }}
                                        </span>
                                        <div class="flex flex-wrap gap-1">
                                            @if($eleve->pivot->responsable_legal)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300" title="Responsable légal de l'enfant">
                                                    Légal
                                                </span>
                                            @endif
                                            @if($eleve->pivot->responsable_paiement)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300" title="Responsable financier des paiements">
                                                    Financier
                                                </span>
                                            @endif
                                            @if($eleve->pivot->autorise_recuperation)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300" title="Autorisé à récupérer l'enfant">
                                                    Récupération
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- Action vers la fiche élève --}}
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    @if($ficheUrl)
                                        <a
                                            href="{{ $ficheUrl }}"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-primary-700 bg-primary-50 hover:bg-primary-100 dark:bg-primary-950/40 dark:text-primary-300 dark:hover:bg-primary-900/50 transition-colors shadow-sm ring-1 ring-primary-500/20"
                                            title="Consulter la fiche complète de l'élève (scolarité, classe, versements)"
                                        >
                                            <x-filament::icon icon="heroicon-o-eye" class="h-3.5 w-3.5 text-primary-600" />
                                            <span>Fiche élève</span>
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-400 italic">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</div>
