@if ($module)
    @php($links = \App\Filament\Support\ModuleExperience::shortcuts($module['module']))
    @if ($links)
        <section class="amb-admin-section amb-module-shortcuts" aria-label="{{ __('Accès rapides') }}">
            <div class="amb-admin-section__heading">
                <div class="amb-admin-section__symbol"><x-filament::icon icon="heroicon-o-bolt" /></div>
                <div><h2>{{ __('Accès rapides') }}</h2><p>{{ __('Choisissez une rubrique pour consulter ses informations et réaliser vos opérations.') }}</p></div>
            </div>
            <div class="amb-admin-cards">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" class="amb-admin-card {{ $loop->even ? 'amb-admin-card--gold' : 'amb-admin-card--blue' }}">
                        <span class="amb-admin-card__icon"><x-filament::icon :icon="$link['icon']" /></span>
                        <h3>{{ $link['title'] }}</h3>
                        <p>{{ $link['description'] }}</p>
                        <span class="amb-admin-card__footer"><span>{{ __('Accéder') }}</span><x-filament::icon icon="heroicon-o-arrow-up-right" /></span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endif
