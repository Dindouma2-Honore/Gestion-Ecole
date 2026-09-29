<x-filament-panels::page>
    <div class="max-w-2xl mx-auto p-6 bg-white dark:bg-gray-900 rounded-xl shadow-md border border-gray-200 dark:border-gray-800">
        <div class="mb-6 text-center">
            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center text-amber-600 dark:text-amber-400">
                <x-filament::icon icon="heroicon-o-shield-exclamation" class="w-7 h-7" />
            </div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Première Connexion Sécurisée</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Pour des raisons de sécurité, vous devez modifier votre mot de passe temporaire avant d'accéder à votre espace de travail.
            </p>
        </div>

        <form wire:submit="submit" class="space-y-6">
            {{ $this->form }}

            <div class="pt-2">
                <x-filament::button type="submit" size="lg" class="w-full">
                    Enregistrer mon nouveau mot de passe
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
