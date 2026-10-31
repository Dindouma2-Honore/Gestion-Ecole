<x-filament-panels::page>
    <div class="space-y-6">
        <div><h2 class="text-lg font-semibold text-gray-950 dark:text-white">Centre opérationnel du jour</h2><p class="text-sm text-gray-500">Sécurité, santé et situations nécessitant une action.</p></div>
        <div class="amb-module-indicators grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($this->getIndicators() as $indicator)
                <x-module-stat :label="$indicator['label']" :value="number_format($indicator['value'], 0, ',', ' ')" :icon="$indicator['icon']" :color="$indicator['color'] ?? null" />
            @endforeach
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"><div class="border-b px-5 py-4 dark:border-white/10"><h3 class="font-semibold">Actions prioritaires</h3></div><div class="divide-y dark:divide-white/10">@forelse($this->getPriorityActions() as $action)<div class="px-5 py-3"><p class="text-sm font-semibold">{{ $action['label'] }}</p><p class="text-sm text-gray-500">{{ \Illuminate\Support\Str::limit($action['detail'],100) }}</p></div>@empty<p class="px-5 py-8 text-center text-sm text-gray-500">Aucune action prioritaire.</p>@endforelse</div></div>
    </div>
</x-filament-panels::page>
