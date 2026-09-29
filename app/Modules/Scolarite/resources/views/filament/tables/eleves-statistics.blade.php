
<div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">

    {{-- Total --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-4">

            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-400">
                <x-heroicon-o-users class="h-6 w-6" />
            </div>

            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $titre ?? 'Total élèves' }}
                </div>

                <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ $total }}
                </div>
            </div>

        </div>
    </div>


    {{-- Filles --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-4">

            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-pink-50 text-pink-600 dark:bg-pink-900/20 dark:text-pink-400">
                <x-heroicon-o-user class="h-6 w-6" />
            </div>

            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Filles
                </div>

                <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ $filles }}
                </div>
            </div>

        </div>
    </div>


    {{-- Garçons --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-4">

            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400">
                <x-heroicon-o-user class="h-6 w-6" />
            </div>

            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Garçons
                </div>

                <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ $garcons }}
                </div>
            </div>

        </div>
    </div>

</div>
