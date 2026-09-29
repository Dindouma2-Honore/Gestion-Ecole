<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a href="{{ $urls['caisse'] }}" class="block">
            <x-filament::section>
                <p class="text-sm text-gray-500">Solde caisse actuel</p>
                <p class="mt-2 text-2xl font-bold text-primary-600">
                    {{ $soldeCaisse !== null ? number_format($soldeCaisse, 0, ',', ' ').' FCFA' : '—' }}
                </p>
                @if ($soldeCaisse === null)
                    <p class="mt-1 text-xs text-warning-600">Aucune caisse ouverte aujourd'hui.</p>
                @endif
            </x-filament::section>
        </a>

        <a href="{{ $urls['depenses'] }}" class="block">
            <x-filament::section>
                <p class="text-sm text-gray-500">Dépenses du mois</p>
                <p class="mt-2 text-2xl font-bold text-danger-600">{{ number_format($depensesDuMois, 0, ',', ' ') }} FCFA</p>
            </x-filament::section>
        </a>

        <a href="{{ $urls['impayes'] }}" class="block">
            <x-filament::section>
                <p class="text-sm text-gray-500">Total impayés élèves</p>
                <p class="mt-2 text-2xl font-bold text-warning-600">{{ number_format($totalImpayes, 0, ',', ' ') }} FCFA</p>
            </x-filament::section>
        </a>

        <a href="{{ $urls['salaires'] }}" class="block">
            <x-filament::section>
                <p class="text-sm text-gray-500">Salaires dus</p>
                <p class="mt-2 text-2xl font-bold text-warning-600">{{ number_format($salairesDus, 0, ',', ' ') }} FCFA</p>
            </x-filament::section>
        </a>
    </div>
</x-filament-panels::page>
