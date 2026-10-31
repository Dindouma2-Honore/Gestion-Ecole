<div class="space-y-5">
    <div class="flex flex-col gap-4 rounded-2xl bg-slate-50 p-5 sm:flex-row sm:items-center dark:bg-slate-900">
        @if ($record->photo)
            <img src="{{ Storage::disk('public')->url($record->photo) }}" alt="Photo de {{ $record->nom_complet }}" class="h-20 w-20 rounded-full object-cover ring-4 ring-white dark:ring-slate-800">
        @else
            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-xl font-bold text-blue-800 dark:bg-blue-950 dark:text-blue-200">
                {{ mb_strtoupper(mb_substr($record->prenom, 0, 1).mb_substr($record->nom, 0, 1)) }}
            </div>
        @endif
        <div>
            <h3 class="text-xl font-bold text-slate-950 dark:text-white">{{ $record->nom_complet }}</h3>
            <p class="mt-1 font-mono text-sm text-slate-500">{{ $record->matricule }}</p>
            <span class="mt-2 inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-800 dark:bg-blue-950 dark:text-blue-200">{{ ucfirst($record->statut) }}</span>
        </div>
    </div>

    @php
        $contrat = $record->contratPrincipal;
        $details = [
            'Rôle' => $record->role?->name,
            'Fonction administrative' => $record->posteAdministratif?->nom,
            'Niveau d’enseignement' => $record->niveau?->name,
            'Téléphone' => $record->telephone,
            'E-mail' => $record->email,
            'Date de naissance' => $record->date_naissance?->format('d/m/Y'),
            'Date d’embauche' => $record->date_embauche?->format('d/m/Y'),
            'Type de contrat' => $contrat?->type,
            'Mode de rémunération' => match ($contrat?->categorie_paie) { 'fixe' => 'Salaire de base', 'horaire' => 'Taux horaire', 'mixte' => 'Fixe + horaire', default => null },
            'Salaire de base' => $contrat ? number_format((float) $contrat->salaire_base, 0, ',', ' ').' FCFA' : null,
            'Taux horaire' => $contrat ? number_format((float) $contrat->taux_horaire, 0, ',', ' ').' FCFA/h' : null,
            'Catégories' => $record->categories->pluck('nom')->join(', '),
        ];
    @endphp

    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-white/10">
        <table class="w-full table-fixed text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-white/5"><tr><th class="px-4 py-3">Information</th><th class="px-4 py-3">Valeur</th></tr></thead>
            <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                @foreach ($details as $label => $value)
                    <tr class="transition hover:bg-blue-50/60 dark:hover:bg-white/5"><th class="px-4 py-3 font-medium text-slate-500">{{ $label }}</th><td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ filled($value) ? $value : 'Non renseigné' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($record->diplomes->isNotEmpty())
        <div class="space-y-2">
            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Diplômes & Qualifications ({{ $record->diplomes->count() }})</h4>
            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-white/10">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 font-semibold uppercase text-slate-500 dark:bg-white/5">
                        <tr><th class="px-3 py-2">Intitulé</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">Établissement</th><th class="px-3 py-2">Année</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                        @foreach ($record->diplomes as $d)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $d->nom }}</td>
                                <td class="px-3 py-2">{{ ucfirst($d->type) }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ $d->etablissement ?: '—' }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ $d->annee_obtention ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($record->sanctions->isNotEmpty())
        <div class="space-y-2">
            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Sanctions & Discipline ({{ $record->sanctions->count() }})</h4>
            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-white/10">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 font-semibold uppercase text-slate-500 dark:bg-white/5">
                        <tr><th class="px-3 py-2">Type</th><th class="px-3 py-2">Date</th><th class="px-3 py-2">Motif</th><th class="px-3 py-2">Statut</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                        @foreach ($record->sanctions as $s)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ ucfirst(str_replace('_', ' ', $s->type)) }}</td>
                                <td class="px-3 py-2">{{ $s->date_sanction?->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ $s->motif }}</td>
                                <td class="px-3 py-2">{{ ucfirst($s->statut) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
