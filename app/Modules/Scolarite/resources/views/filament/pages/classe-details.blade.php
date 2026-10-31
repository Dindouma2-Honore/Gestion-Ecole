@php
    $argent = static fn ($montant): string => number_format((float) $montant, 0, ',', ' ').' FCFA';
    $taux = $resume['attendu'] > 0 ? min(100, round($resume['encaisse'] * 100 / $resume['attendu'], 1)) : 0;
@endphp
<div class="space-y-6">
    <x-filament::section heading="Configuration de la salle" icon="heroicon-o-building-library">
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5"><tr><th class="px-4 py-3">Information</th><th class="px-4 py-3">Valeur</th><th class="px-4 py-3">Information</th><th class="px-4 py-3">Valeur</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach([
                        ['Nom', $classe->nom, 'Code', $classe->code ?: 'Non défini'],
                        ['Niveau', $niveau, 'Année scolaire', $annee],
                        ['Capacité maximale', $classe->capacite_max.' élèves', 'Statut', ucfirst($classe->statut)],
                        ['Salle principale', $salle, 'Enseignant principal', $enseignant],
                        ['Section', $section, 'Série / filière', $filiere],
                        ["Frais d'inscription", $argent($configuration['frais_inscription'] ?? config('scolarite.frais_inscription', 15000)), 'Scolarité annuelle', $argent($configuration['montant_total'] ?? 0)],
                    ] as $ligne)
                        <tr class="transition hover:bg-blue-50/60 dark:hover:bg-white/5"><th class="px-4 py-3 font-medium text-gray-500">{{ $ligne[0] }}</th><td class="px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ $ligne[1] }}</td><th class="px-4 py-3 font-medium text-gray-500">{{ $ligne[2] }}</th><td class="px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ $ligne[3] }}</td></tr>
                    @endforeach
                    <tr class="transition hover:bg-blue-50/60 dark:hover:bg-white/5"><th class="px-4 py-3 font-medium text-gray-500">Tranches</th><td colspan="3" class="px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ collect($configuration['tranches'] ?? [])->map(fn ($tranche) => ($tranche['libelle'] ?? 'Tranche').' : '.$argent($tranche['montant'] ?? 0))->implode(' · ') ?: 'Non configurées' }}</td></tr>
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Bilan financier" description="Élèves inscrits pour cette année scolaire uniquement" icon="heroicon-o-chart-bar-square">
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5"><tr><th class="px-4 py-3">Inscrits</th><th class="px-4 py-3">Filles</th><th class="px-4 py-3">Garçons</th><th class="px-4 py-3">Taux de recouvrement</th></tr></thead>
                <tbody><tr class="border-b border-gray-200 transition hover:bg-blue-50/60 dark:border-white/10 dark:hover:bg-white/5"><td class="px-4 py-3 font-bold">{{ $resume['total'] }}</td><td class="px-4 py-3 font-bold">{{ $resume['filles'] }}</td><td class="px-4 py-3 font-bold">{{ $resume['garcons'] }}</td><td class="px-4 py-3 font-bold text-blue-700">{{ number_format($taux, 1, ',', ' ') }} %</td></tr></tbody>
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5"><tr><th class="px-4 py-3">Montant total attendu</th><th class="px-4 py-3">Montant déjà encaissé</th><th class="px-4 py-3">Reste à percevoir</th><th class="px-4 py-3">Situation</th></tr></thead>
                <tbody><tr class="transition hover:bg-blue-50/60 dark:hover:bg-white/5"><td class="px-4 py-3 font-bold">{{ $argent($resume['attendu']) }}</td><td class="px-4 py-3 font-bold text-emerald-600">{{ $argent($resume['encaisse']) }}</td><td class="px-4 py-3 font-bold text-amber-600">{{ $argent($resume['reste']) }}</td><td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $resume['reste'] > 0 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $resume['reste'] > 0 ? 'À recouvrer' : 'Soldée' }}</span></td></tr></tbody>
            </table>
        </div>
    </x-filament::section>
</div>
