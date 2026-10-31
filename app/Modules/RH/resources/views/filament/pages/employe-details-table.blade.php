@php
    $vide = static fn ($valeur): string => filled($valeur) ? (string) $valeur : 'Non renseigné';
    $date = static fn ($valeur): string => $valeur ? \Illuminate\Support\Carbon::parse($valeur)->format('d/m/Y') : 'Non renseignée';
    $argent = static fn ($valeur): string => number_format((float) $valeur, 0, ',', ' ').' FCFA';
    $sexe = match ($record->sexe) { 'F' => 'Féminin', 'M' => 'Masculin', default => 'Non renseigné' };
@endphp
<div class="space-y-6">
    <x-filament::section heading="Identité du membre du personnel" icon="heroicon-o-identification">
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full table-fixed text-left text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-white/5"><tr><th class="px-4 py-3">Information</th><th class="px-4 py-3">Valeur</th><th class="px-4 py-3">Information</th><th class="px-4 py-3">Valeur</th></tr></thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach([['Nom complet', $record->nom_complet, 'Matricule', $record->matricule], ['Sexe', $sexe, 'Date de naissance', $date($record->date_naissance)], ['Téléphone', $vide($record->telephone), 'E-mail', $vide($record->email)]] as $ligne)
                    <tr class="hover:bg-blue-50/60 dark:hover:bg-white/5"><th class="px-4 py-3 font-medium text-gray-500">{{ $ligne[0] }}</th><td class="px-4 py-3 font-semibold">{{ $ligne[1] }}</td><th class="px-4 py-3 font-medium text-gray-500">{{ $ligne[2] }}</th><td class="px-4 py-3 font-semibold">{{ $ligne[3] }}</td></tr>
                @endforeach
            </tbody>
        </table></div>
    </x-filament::section>
    <x-filament::section heading="Affectation, contrat et rémunération" icon="heroicon-o-briefcase">
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full table-fixed text-left text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-white/5"><tr><th class="px-4 py-3">Information</th><th class="px-4 py-3">Valeur</th><th class="px-4 py-3">Information</th><th class="px-4 py-3">Valeur</th></tr></thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach([['Poste', $vide($record->poste), 'Fonction administrative', $record->posteAdministratif?->nom ?? 'Aucune'], ['Rôle', $record->role?->name ?? 'Non attribué', 'Niveau d’enseignement', $record->niveau?->name ?? 'Non renseigné'], ['Date d’embauche', $date($record->date_embauche), 'Type de contrat', $record->contratPrincipal?->type ?? 'Non défini'], ['Mode de rémunération', $record->contratPrincipal?->categorie_paie ?? 'Non défini', 'Salaire de base', $argent($record->contratPrincipal?->salaire_base)], ['Taux horaire', $argent($record->contratPrincipal?->taux_horaire), 'Catégories', $record->categories->pluck('nom')->implode(', ') ?: 'Aucune'], ['Statut', ucfirst((string) $record->statut), '', '']] as $ligne)
                    <tr class="hover:bg-blue-50/60 dark:hover:bg-white/5"><th class="px-4 py-3 font-medium text-gray-500">{{ $ligne[0] }}</th><td class="px-4 py-3 font-semibold">{{ $ligne[1] }}</td><th class="px-4 py-3 font-medium text-gray-500">{{ $ligne[2] }}</th><td class="px-4 py-3 font-semibold">{{ $ligne[3] }}</td></tr>
                @endforeach
            </tbody>
        </table></div>
    </x-filament::section>

    @if ($record->diplomes->isNotEmpty())
        <x-filament::section heading="Diplômes & Qualifications" icon="heroicon-o-academic-cap">
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full table-fixed text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3">Intitulé</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Établissement</th>
                            <th class="px-4 py-3">Année</th>
                            <th class="px-4 py-3">Réf. Document</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($record->diplomes as $diplome)
                            <tr class="hover:bg-blue-50/60 dark:hover:bg-white/5">
                                <td class="px-4 py-3 font-semibold">{{ $diplome->nom }}</td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $diplome->type === 'academique' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800' }}">{{ ucfirst($diplome->type) }}</span></td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $vide($diplome->etablissement) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $diplome->annee_obtention ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $diplome->document_id ? '#'.$diplome->document_id : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if ($record->sanctions->isNotEmpty())
        <x-filament::section heading="Discipline & Sanctions" icon="heroicon-o-shield-exclamation">
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full table-fixed text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Motif</th>
                            <th class="px-4 py-3">Durée (j)</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3">Justificatif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($record->sanctions as $sanction)
                            <tr class="hover:bg-blue-50/60 dark:hover:bg-white/5">
                                <td class="px-4 py-3 font-semibold">{{ ucfirst(str_replace('_', ' ', $sanction->type)) }}</td>
                                <td class="px-4 py-3">{{ $date($sanction->date_sanction) }}</td>
                                <td class="px-4 py-3">{{ $sanction->motif }}</td>
                                <td class="px-4 py-3">{{ $sanction->duree_jours ?? '—' }}</td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $sanction->statut === 'validee' ? 'bg-red-100 text-red-800' : ($sanction->statut === 'annulee' ? 'bg-gray-100 text-gray-800' : 'bg-amber-100 text-amber-800') }}">{{ ucfirst(str_replace('_', ' ', $sanction->statut)) }}</span></td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $sanction->document_justificatif_id ? '#'.$sanction->document_justificatif_id : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if ($record->historiquePostes->isNotEmpty())
        <x-filament::section heading="Évolution de carrière & Postes" icon="heroicon-o-arrow-trending-up">
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full table-fixed text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3">Fonction</th>
                            <th class="px-4 py-3">Période</th>
                            <th class="px-4 py-3">Motif / Commentaire</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($record->historiquePostes as $hist)
                            <tr class="hover:bg-blue-50/60 dark:hover:bg-white/5">
                                <td class="px-4 py-3 font-semibold">{{ $hist->poste?->nom ?? $hist->poste_occupe ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $date($hist->date_debut) }} &rarr; {{ $hist->date_fin ? $date($hist->date_fin) : 'Poste actuel' }}</td>
                                <td class="px-4 py-3">{{ $vide($hist->motif_changement) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if ($record->candidature?->cv_path)
        <x-filament::section heading="Documents administratifs" icon="heroicon-o-folder-open">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    CV de candidature : <span class="font-mono text-xs">{{ $record->candidature->cv_path }}</span>
                </p>
            </div>
        </x-filament::section>
    @endif
</div>
