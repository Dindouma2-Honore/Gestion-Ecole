<x-filament-panels::page>
    @php($context = $this->getContext())
    <div class="space-y-6">
    <x-filament::section icon="heroicon-o-funnel" icon-color="primary">
        <x-slot name="heading">Périmètre pédagogique</x-slot>
        <x-slot name="description">Les indicateurs se recalculent automatiquement selon le niveau, la classe et la période choisis.</x-slot>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Niveau<select wire:model.live="cycleId" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"><option value="">Tous les niveaux</option>@foreach($context['cycles'] as $id=>$nom)<option value="{{ $id }}">{{ $nom }}</option>@endforeach</select></label>
            <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Classe<select wire:model.live="classeId" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"><option value="">Toutes les classes</option>@foreach($context['classes'] as $id=>$nom)<option value="{{ $id }}">{{ $nom }}</option>@endforeach</select></label>
            <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Période scolaire<select wire:model.live="periodeId" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"><option value="">Toutes les périodes</option>@foreach($context['periodes'] as $id=>$nom)<option value="{{ $id }}">{{ $nom }}</option>@endforeach</select></label>
            <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Du<input wire:model.live="dateDebut" type="date" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"></label>
            <label class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white">Au<input wire:model.live="dateFin" type="date" class="rounded-lg border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"></label>
            <div class="grid gap-2 text-sm font-medium text-gray-950 dark:text-white"><span>Établissement</span><div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 shadow-sm dark:border-gray-700 dark:bg-gray-900">{{ $context['etablissement'] }}</div></div>
        </div>
        <div class="mt-5 flex items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-white/10"><p class="text-sm text-gray-500">Vue contextualisée de l’activité pédagogique</p><x-filament::button wire:click="reinitialiserFiltres" color="gray" icon="heroicon-o-arrow-path">Réinitialiser</x-filament::button></div>
    </x-filament::section>
    <div class="amb-module-indicators grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach ($this->getIndicators() as $index => $indicator)
            <x-module-stat :label="$indicator['label']" :value="$indicator['value']" :url="$indicator['url']" :icon="$index < 4 ? 'heroicon-o-calendar-days' : 'heroicon-o-chart-bar-square'" />
        @endforeach
    </div>
    <x-filament::section heading="Anomalies à traiter" description="Éléments nécessitant une action pédagogique.">
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5"><tr><th class="p-3">Classe</th><th class="p-3">Matière</th><th class="p-3">Anomalie</th><th class="p-3 text-right">Action</th></tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->getAnomalies() as $anomalie)
                        <tr class="hover:bg-primary-50/50 dark:hover:bg-white/5"><td class="p-3">{{ $anomalie['classe'] }}</td><td class="p-3">{{ $anomalie['matiere'] }}</td><td class="p-3">{{ $anomalie['type'] }}</td><td class="p-3 text-right"><a class="font-semibold text-primary-600" href="{{ $anomalie['url'] }}">Traiter</a></td></tr>
                    @empty
                        <tr><td colspan="4" class="p-8 text-center text-gray-500">Aucune anomalie à traiter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
    <div class="flex flex-wrap gap-3 text-sm">
        <a class="font-semibold text-primary-700 dark:text-primary-300" href="{{ \App\Modules\Pedagogie\Filament\Resources\MatiereResource::getUrl() }}">Configurer les matières</a>
        <span class="text-gray-300">•</span>
        <a class="font-semibold text-primary-700 dark:text-primary-300" href="{{ \App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource::getUrl() }}">Ouvrir les emplois du temps</a>
    </div>
    </div>
</x-filament-panels::page>
