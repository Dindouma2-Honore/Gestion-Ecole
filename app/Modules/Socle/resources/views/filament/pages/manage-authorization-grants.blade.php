<x-filament-panels::page>
    <div class="mx-auto max-w-4xl space-y-6">
        <form wire:submit="enregistrer" class="space-y-4">
            {{ $this->form }}

            <x-filament::button type="submit" icon="heroicon-o-check">
                Enregistrer le grant
            </x-filament::button>
        </form>

        <x-filament::section
            heading="Grants existants (Authorization V1)"
            description="Chaque ligne correspond à un grant Authorization V1. Désactiver conserve la ligne et son historique."
        >
            <div class="space-y-2">
                @forelse ($this->grants() as $grant)
                    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ $grant->feature?->nom }} <span class="text-gray-500">({{ $grant->feature?->code }})</span>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $grant->subject_type->value }} #{{ $grant->subject_id }}
                                ·
                                @if ($grant->grantedActions() !== [])
                                    {{ implode(', ', array_map(static fn ($action): string => strtoupper($action->value), $grant->grantedActions())) }}
                                @else
                                    AUCUNE ACTION
                                @endif
                                ·
                                <span @class(['font-semibold' => $grant->is_active])>
                                    {{ $grant->is_active ? 'Actif' : 'Inactif' }}
                                </span>
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <x-filament::button
                                size="xs"
                                color="gray"
                                icon="heroicon-o-pencil-square"
                                wire:click="modifier({{ $grant->id }})"
                            >
                                Modifier
                            </x-filament::button>
                            <x-filament::button
                                size="xs"
                                :color="$grant->is_active ? 'danger' : 'success'"
                                :icon="$grant->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye'"
                                wire:click="basculerEtat({{ $grant->id }})"
                            >
                                {{ $grant->is_active ? 'Désactiver' : 'Réactiver' }}
                            </x-filament::button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Aucun grant Authorization V1 enregistré pour le moment. La table authorization_grants peut rester
                        vide tant que la transition n'a pas commencé.
                    </p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
