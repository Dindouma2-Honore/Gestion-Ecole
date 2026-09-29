<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-col items-center px-4 py-8 text-center sm:px-6 sm:py-12">
            <div class="flex size-16 items-center justify-center rounded-2xl bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                <x-filament::icon :icon="$this->getModuleIcon()" class="size-9" />
            </div>
            <h2 class="mt-5 text-xl font-bold text-gray-950 dark:text-white">{{ $this->getTitle() }}</h2>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $this->getModuleDescription() }}</p>
        </div>
    </x-filament::section>

    @if (method_exists($this, 'getModuleLinks'))
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->getModuleLinks() as $link)
                <a href="{{ $link['url'] }}" class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-primary-400 hover:shadow-lg dark:border-white/10 dark:bg-gray-900">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-primary-50 text-primary-700 group-hover:bg-primary-600 group-hover:text-white dark:bg-primary-500/10 dark:text-primary-300">
                        <x-filament::icon :icon="$link['icon']" class="size-6" />
                    </div>
                    <h3 class="mt-4 font-bold text-gray-950 dark:text-white">{{ $link['label'] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $link['description'] }}</p>
                    <span class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-primary-700 dark:text-primary-300">
                        Ouvrir <x-filament::icon icon="heroicon-m-arrow-right" class="size-4" />
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" class="inline-flex items-center gap-2 text-sm font-semibold text-primary-700 dark:text-primary-300">
        <x-filament::icon icon="heroicon-m-arrow-left" class="size-4" />
        Retour à l’accueil des modules
    </a>
</x-filament-panels::page>
