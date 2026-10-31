<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <div class="grid gap-4 md:grid-cols-2">
            <x-filament::section>
                <div class="text-sm text-gray-500">Total des salaires payés ou validés</div>
                <div class="mt-1 text-2xl font-bold">{{ number_format($this->getTotalPaies(), 0, ',', ' ') }} FCFA</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500">Total des avances enregistrées</div>
                <div class="mt-1 text-2xl font-bold">{{ number_format($this->getTotalAvances(), 0, ',', ' ') }} FCFA</div>
            </x-filament::section>
        </div>

        @if (($data['type'] ?? 'tous') !== 'avance')
            @livewire(\App\Modules\RH\Filament\Widgets\HistoriquePaiesTable::class, ['filters' => $data ?? [], 'type' => 'paie'], key('historique-salaires'))
        @endif
        @if (($data['type'] ?? 'tous') !== 'paie')
            @livewire(\App\Modules\RH\Filament\Widgets\HistoriquePaiesTable::class, ['filters' => $data ?? [], 'type' => 'avance'], key('historique-avances'))
        @endif
    </div>
</x-filament-panels::page>
