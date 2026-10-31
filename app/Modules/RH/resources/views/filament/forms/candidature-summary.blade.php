@php
    $vide = static fn ($valeur): string => filled($valeur) ? (string) $valeur : 'Non renseigné';
    $date = static fn ($valeur): string => $valeur ? \Illuminate\Support\Carbon::parse($valeur)->format('d/m/Y') : 'Non renseignée';
    $dateHeure = static fn ($valeur): string => $valeur ? \Illuminate\Support\Carbon::parse($valeur)->format('d/m/Y H:i') : 'Non renseignée';
    $statuts = \App\Modules\RH\Filament\Resources\CandidatureResource::statuts();
@endphp

@if($record)
    <div class="space-y-6">
        <x-filament::section heading="Identité du candidat" icon="heroicon-o-identification">
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full table-fixed text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3">Information</th>
                            <th class="px-4 py-3">Valeur</th>
                            <th class="px-4 py-3">Information</th>
                            <th class="px-4 py-3">Valeur</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach([
                            ['Nom complet', $record->nom_complet, 'Référence', $record->reference],
                            ['Téléphone', $vide($record->telephone), 'E-mail', $vide($record->email)],
                            ['Poste recherché', $record->poste_souhaite, 'Statut', $statuts[$record->statut] ?? $record->statut],
                        ] as $ligne)
                            <tr class="hover:bg-blue-50/60 dark:hover:bg-white/5">
                                <th class="px-4 py-3 font-medium text-gray-500">{{ $ligne[0] }}</th>
                                <td class="px-4 py-3 font-semibold">{{ $ligne[1] }}</td>
                                <th class="px-4 py-3 font-medium text-gray-500">{{ $ligne[2] }}</th>
                                <td class="px-4 py-3 font-semibold">{{ $ligne[3] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Suivi du recrutement" icon="heroicon-o-briefcase">
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full table-fixed text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3">Information</th>
                            <th class="px-4 py-3">Valeur</th>
                            <th class="px-4 py-3">Information</th>
                            <th class="px-4 py-3">Valeur</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach([
                            ['Date du dossier', $date($record->created_at), 'Date d’entretien', $dateHeure($record->date_entretien)],
                            ['Évalué par', $record->evaluateur?->name ?? 'Non renseigné', 'CV', $record->cv_path ? 'Déposé' : 'Non déposé'],
                        ] as $ligne)
                            <tr class="hover:bg-blue-50/60 dark:hover:bg-white/5">
                                <th class="px-4 py-3 font-medium text-gray-500">{{ $ligne[0] }}</th>
                                <td class="px-4 py-3 font-semibold">{{ $ligne[1] }}</td>
                                <th class="px-4 py-3 font-medium text-gray-500">{{ $ligne[2] }}</th>
                                <td class="px-4 py-3 font-semibold">{{ $ligne[3] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
@endif
