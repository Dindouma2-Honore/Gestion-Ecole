<x-filament-panels::page>
    <div class="space-y-6">
        <div><h2 class="text-lg font-semibold text-gray-950 dark:text-white">Centre de pilotage logistique</h2><p class="text-sm text-gray-500">Les alertes qui demandent une action, sans indicateurs superflus.</p></div>
        <div class="amb-module-indicators grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->getIndicators() as $indicator)
                <x-module-stat :label="$indicator['label']" :value="number_format($indicator['value'], 0, ',', ' ')" :icon="$indicator['icon']" :color="$indicator['color'] ?? null" />
            @endforeach
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10"><h3 class="font-semibold text-gray-950 dark:text-white">Actions prioritaires</h3></div>
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($this->getPriorityActions() as $action)
                    <div class="flex items-center justify-between gap-4 px-5 py-3 text-sm"><div><p class="font-medium text-gray-950 dark:text-white">{{ $action->equipement?->nom ?? $action->salle?->nom ?? 'Intervention logistique' }}</p><p class="text-gray-500">{{ \Illuminate\Support\Str::limit($action->description, 80) }}</p></div><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold dark:bg-white/10">{{ ucfirst($action->priorite ?? 'normale') }}</span></div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-gray-500">Aucune intervention prioritaire ouverte.</p>
                @endforelse
            </div>
        </div>

    </div>
</x-filament-panels::page>
