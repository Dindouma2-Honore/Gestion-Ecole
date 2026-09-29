<x-filament-widgets::widget>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><h2 class="text-xl font-bold text-gray-950 dark:text-white">{{ __('finance.dashboard.heading') }}</h2><p class="mt-1 text-sm text-gray-500">{{ __('finance.dashboard.subtitle') }}</p></div>
            <x-filament::button tag="a" :href="$urls['caisse']" :color="$sessionOuverte ? 'gray' : 'danger'" icon="heroicon-o-lock-open">{{ $sessionOuverte ? __('finance.dashboard.manage_cash') : __('finance.dashboard.open_cash') }}</x-filament::button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                [__('finance.dashboard.cash_balance'), $solde === null ? '—' : number_format($solde, 0, ',', ' ').' FCFA', $sessionOuverte ? __('finance.dashboard.cash_open') : __('finance.dashboard.cash_closed'), 'heroicon-o-banknotes', $sessionOuverte ? 'success' : 'danger'],
                [__('finance.dashboard.today_income'), number_format($balance->total_entrees, 0, ',', ' ').' FCFA', __('finance.dashboard.money_received'), 'heroicon-o-arrow-trending-up', 'success'],
                [__('finance.dashboard.today_expenses'), number_format($balance->total_sorties, 0, ',', ' ').' FCFA', __('finance.dashboard.money_paid'), 'heroicon-o-arrow-trending-down', 'danger'],
                [__('finance.dashboard.unpaid_school_fees'), number_format($impayes, 0, ',', ' ').' FCFA', __('finance.dashboard.to_collect'), 'heroicon-o-exclamation-triangle', $impayes > 0 ? 'warning' : 'success'],
            ] as [$label, $value, $description, $icon, $color])
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-sm font-medium text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $value }}</p><p class="mt-1 text-xs text-gray-500">{{ $description }}</p></div><x-filament::icon :icon="$icon" @class(['h-7 w-7', 'text-success-600' => $color === 'success', 'text-danger-600' => $color === 'danger', 'text-warning-600' => $color === 'warning']) /></div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-5">
            <div class="lg:col-span-2 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="font-semibold text-gray-950 dark:text-white">{{ __('finance.dashboard.attention') }}</h3>
                <div class="mt-4 space-y-3">
                    <a href="{{ $urls['inscriptions'] }}" class="flex items-center justify-between rounded-xl bg-warning-50 p-4 transition hover:bg-warning-100 dark:bg-warning-500/10"><span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('finance.dashboard.registrations_to_check') }}</span><x-filament::badge color="warning">{{ $facturesAControler }}</x-filament::badge></a>
                    <a href="{{ $urls['depenses'] }}" class="flex items-center justify-between rounded-xl bg-danger-50 p-4 transition hover:bg-danger-100 dark:bg-danger-500/10"><span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('finance.dashboard.expenses_to_approve') }}</span><x-filament::badge color="danger">{{ $depensesAValider }}</x-filament::badge></a>
                </div>
            </div>
            <div class="lg:col-span-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="font-semibold text-gray-950 dark:text-white">{{ __('finance.dashboard.quick_actions') }}</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ([['paiements', 'heroicon-o-banknotes', 'payments'], ['depenses', 'heroicon-o-arrow-trending-down', 'expenses'], ['situation', 'heroicon-o-building-library', 'overview'], ['balance', 'heroicon-o-scale', 'balance'], ['caisse', 'heroicon-o-lock-open', 'cash']] as [$url, $icon, $label])
                        <a href="{{ $urls[$url] }}" class="flex items-center gap-3 rounded-xl border border-gray-200 p-3 text-sm font-semibold text-gray-700 transition hover:border-primary-400 hover:bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:text-gray-200 dark:hover:bg-primary-500/10"><x-filament::icon :icon="$icon" class="h-5 w-5" />{{ __('finance.dashboard.'.$label) }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
