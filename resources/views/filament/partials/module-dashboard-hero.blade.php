@if ($module)
    <header class="amb-admin-hero amb-module-hero">
        <img class="amb-admin-hero__campus" src="{{ asset('images/campus.jpg') }}" alt="" aria-hidden="true">
        <div class="amb-admin-hero__content">
            <span class="amb-admin-eyebrow">Ambassadors Educational Complex</span>
            <h1>{{ $module['name'] }}</h1>
            <p class="amb-admin-hero__description">{{ $module['description'] }}</p>
            <span class="amb-admin-signature"><span></span>{{ __('Tableau de bord') }}</span>
        </div>
        <div class="amb-admin-date"><x-filament::icon icon="heroicon-o-calendar-days" /><div><span>{{ __('Aujourd’hui') }}</span><time datetime="{{ now()->toDateString() }}">{{ now()->locale(app()->getLocale())->translatedFormat('d F Y') }}</time></div></div>
    </header>
@endif
