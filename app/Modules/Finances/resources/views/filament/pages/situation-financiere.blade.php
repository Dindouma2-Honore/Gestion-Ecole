<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input wire:model.live.debounce.300ms="recherche" type="search" placeholder="Rechercher un élève…" class="rounded-xl border-gray-300">
            <select wire:model.live="niveauId" class="rounded-xl border-gray-300"><option value="">Tous les niveaux</option>@foreach($niveaux as $id => $nom)<option value="{{ $id }}">{{ $nom }}</option>@endforeach</select>
            <select wire:model.live="classeId" class="rounded-xl border-gray-300"><option value="">Toutes les classes</option>@foreach($classes as $id => $nom)<option value="{{ $id }}">{{ $nom }}</option>@endforeach</select>
            <select wire:model.live="sexe" class="rounded-xl border-gray-300"><option value="">Tous les sexes</option><option value="M">Masculin</option><option value="F">Féminin</option></select>
        </div>
        <div class="mt-4"><p class="mb-2 text-sm font-semibold">Colonnes de l’export</p><div class="flex flex-wrap gap-4">@foreach($this->colonnesDisponibles() as $code => $label)<label><input type="checkbox" wire:model="colonnesExport" value="{{ $code }}"> {{ $label }}</label>@endforeach</div></div>
        <div class="mt-4 flex gap-3"><x-filament::button wire:click="exporterCsv">Exporter Excel/CSV</x-filament::button><x-filament::button color="gray" wire:click="exporterPdf">Exporter PDF</x-filament::button></div>
    </x-filament::section>
    <x-filament::section>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-3">Élève</th><th class="p-3">Classe</th><th class="p-3 text-right">Dû</th><th class="p-3 text-right">Payé</th><th class="p-3 text-right">Reste</th><th class="p-3">Statut</th></tr></thead><tbody>
        @forelse($lignes as $ligne)<tr class="border-b"><td class="p-3 font-medium">{{ $ligne['eleve'] }}</td><td class="p-3">{{ $ligne['classe'] }}</td><td class="p-3 text-right">{{ number_format($ligne['du'], 0, ',', ' ') }}</td><td class="p-3 text-right text-success-600">{{ number_format($ligne['paye'], 0, ',', ' ') }}</td><td class="p-3 text-right text-danger-600">{{ number_format($ligne['reste'], 0, ',', ' ') }}</td><td class="p-3">{{ ucfirst($ligne['statut']) }}</td></tr>
        @empty<tr><td colspan="6" class="p-8 text-center text-gray-500">Aucun élève ne correspond aux filtres.</td></tr>@endforelse
        </tbody></table></div>
    </x-filament::section>
    <x-filament::section heading="Synthèse par frais individuel"><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th class="p-2 text-left">Frais</th><th>Attendu</th><th>Reçu</th><th>Restant</th></tr></thead><tbody>@foreach($totauxParFrais as $frais)<tr class="border-t"><td class="p-2">{{ $frais['libelle'] }}</td><td class="text-right">{{ number_format($frais['attendu'],0,',',' ') }}</td><td class="text-right">{{ number_format($frais['recu'],0,',',' ') }}</td><td class="text-right">{{ number_format($frais['restant'],0,',',' ') }}</td></tr>@endforeach</tbody></table></div></x-filament::section>
</x-filament-panels::page>
