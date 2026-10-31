@php($currentModule = collect(\App\Filament\Support\ModuleCatalog::visibleFor(auth()->user()))->firstWhere('module', \App\Filament\Support\ModuleNavigation::currentModuleCode()))
@if ($currentModule)
    <nav class="amb-admin-topbar" aria-label="{{ __('Navigation générale') }}">
        <a href="{{ route('home') }}" class="amb-admin-topbar__home" title="{{ __('Accueil des modules') }}">
            <x-filament::icon icon="heroicon-o-squares-2x2" />
            <span>{{ __('Modules') }}</span>
        </a>
        <span class="amb-admin-topbar__divider" aria-hidden="true">/</span>
        <a href="{{ $currentModule['url'] }}" class="amb-admin-topbar__current">{{ $currentModule['name'] }}</a>
    </nav>
@endif
