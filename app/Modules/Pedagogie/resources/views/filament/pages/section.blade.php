<x-filament-panels::page>
    <p class="max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $this->getSectionDescription() }}</p>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->getSectionLinks() as $link)
            <a href="{{ $link['url'] }}" class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary-400 hover:shadow-md dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-start gap-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 group-hover:bg-primary-600 group-hover:text-white dark:bg-primary-500/10 dark:text-primary-300">
                        <x-filament::icon :icon="$link['icon']" class="size-5" />
                    </span>
                    <span>
                        <strong class="block text-sm text-gray-950 dark:text-white">{{ $link['label'] }}</strong>
                        <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $link['description'] }}</span>
                    </span>
                </div>
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
