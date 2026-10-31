<x-filament-panels::page>
    <x-filament::section>
        <p>{{ $this->notice() }}</p>
    </x-filament::section>
    @if ($this instanceof \App\Modules\Technique\Filament\Pages\Backups)
        <x-filament::section heading="Sauvegardes de la base de données">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600 dark:text-gray-300">Les fichiers sont conservés dans le stockage privé du serveur et contrôlés par empreinte SHA-256.</p>
                @if ($this->canCreateBackup())
                    <x-filament::button wire:click="createBackup" wire:loading.attr="disabled" wire:target="createBackup" icon="heroicon-o-archive-box-arrow-down">
                        Créer une sauvegarde
                    </x-filament::button>
                @endif
            </div>
            <div class="space-y-3">
                @forelse ($this->backups() as $backup)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="min-w-0">
                            <p class="break-all font-medium">{{ $backup['file'] }}</p>
                            <p class="text-sm text-gray-500">{{ $backup['created_at'] }} · {{ number_format($backup['size'] / 1048576, 2) }} Mo · {{ strtoupper($backup['driver']) }} · empreinte SHA-256 vérifiée avant restauration</p>
                        </div>
                        @if ($this->canRestoreBackup())
                            <x-filament::button color="danger" size="sm" icon="heroicon-o-arrow-path" wire:click="restoreBackup(@js($backup['file']))" wire:confirm.prompt="Cette opération remplace les données actuelles. Un point de retour sera créé automatiquement.\n\nPour confirmer, saisissez RESTAURER|RESTAURER" wire:loading.attr="disabled" wire:target="restoreBackup">
                                Restaurer
                            </x-filament::button>
                        @endif
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/20">Aucune sauvegarde vérifiée pour cette base.</p>
                @endforelse
            </div>
        </x-filament::section>
    @endif
    @if ($this instanceof \App\Modules\Technique\Filament\Pages\TechnicalDashboard || $this instanceof \App\Modules\Technique\Filament\Pages\Databases || $this instanceof \App\Modules\Technique\Filament\Pages\ScheduledTasks)
        <div wire:poll.30s class="grid gap-4 md:grid-cols-2">
            @foreach ($this->measurements() as $component => $measurement)
                <x-filament::section :heading="$component">
                    <p>{{ ['UNKNOWN' => 'Inconnu — mesure absente ou expirée', 'AVAILABLE' => 'Disponible', 'FAILED' => 'Échec'][$measurement['status']] ?? 'Inconnu' }}</p>
                    <p>Dernière mesure : {{ $measurement['measured_at'] ?? 'Aucune' }}</p>
                    @foreach ($measurement['metrics'] as $name => $value)
                        <p>{{ $name }} : {{ $value }}</p>
                    @endforeach
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
