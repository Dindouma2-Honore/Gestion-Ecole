<x-filament-panels::page class="amb-administration">
    <div class="amb-admin-home">
        <header class="amb-admin-hero">
            <img class="amb-admin-hero__campus" src="{{ asset('images/campus.jpg') }}" alt="" aria-hidden="true">
            <div class="amb-admin-hero__content">
                <span class="amb-admin-eyebrow">{{ __('Ambassadors Educational Complex') }}</span>
                <h1>{{ __('Administration') }}</h1>
                <p>{{ __('Les équipes, les accès et l’organisation de votre établissement, réunis au même endroit.') }}</p>
                <span class="amb-admin-signature"><span></span>{{ __('Un cadre commun pour toute l’école') }}</span>
            </div>
            <div class="amb-admin-date">
                <x-filament::icon icon="heroicon-o-calendar-days" />
                <div><span>{{ __('Aujourd’hui') }}</span><time datetime="{{ now()->toDateString() }}">{{ now()->locale(app()->getLocale())->isoFormat('D MMMM YYYY') }}</time></div>
            </div>
        </header>

        @if (count($stats))
            <section class="amb-admin-stats" aria-label="{{ __('Chiffres clés de l’administration') }}">
                @foreach ($stats as $stat)
                    <article class="amb-admin-stat">
                        <div class="amb-admin-stat__icon"><x-filament::icon :icon="$stat['icon']" /></div>
                        <div><h2>{{ __($stat['title']) }}</h2><strong>{{ number_format($stat['value'], 0, ',', ' ') }}</strong><p>{{ __($stat['detail']) }}</p></div>
                    </article>
                @endforeach
            </section>
        @endif

        <section class="amb-admin-section" aria-labelledby="admin-quick-heading">
            <div class="amb-admin-section__heading"><div class="amb-admin-section__symbol"><x-filament::icon icon="heroicon-o-bolt" /></div><div><h2 id="admin-quick-heading">{{ __('Accès rapides') }}</h2><p>{{ __('Les outils essentiels pour piloter votre administration.') }}</p></div></div>
            <div class="amb-admin-cards">
                @foreach (collect($links)->where('primary', true) as $link)
                    <a href="{{ $link['url'] }}" class="amb-admin-card amb-admin-card--{{ $link['tone'] }}">
                        <span class="amb-admin-card__icon"><x-filament::icon :icon="$link['icon']" /></span>
                        <h3>{{ __($link['title']) }}</h3><p>{{ __($link['description']) }}</p>
                        <span class="amb-admin-card__footer"><span>{{ __('Accéder') }}</span><x-filament::icon icon="heroicon-o-arrow-up-right" /></span>
                    </a>
                @endforeach
            </div>
        </section>

        @if (collect($links)->where('primary', false)->isNotEmpty())
            <section class="amb-admin-section amb-admin-section--secondary" aria-labelledby="admin-organise-heading">
                <div class="amb-admin-section__heading"><div><h2 id="admin-organise-heading">{{ __('Au quotidien') }}</h2><p>{{ __('Les ressources pour une administration bien organisée.') }}</p></div></div>
                <div class="amb-admin-shortcuts">
                    @foreach (collect($links)->where('primary', false) as $link)
                        <a href="{{ $link['url'] }}"><x-filament::icon :icon="$link['icon']" /><span>{{ __($link['title']) }}</span><x-filament::icon class="amb-admin-shortcut-arrow" icon="heroicon-o-chevron-right" /></a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
