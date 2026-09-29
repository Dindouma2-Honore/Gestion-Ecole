<x-filament-panels::page>
    <form wire:submit="create" class="mx-auto max-w-3xl space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" icon="heroicon-o-check">
            Enregistrer le frais
        </x-filament::button>
    </form>
</x-filament-panels::page>
