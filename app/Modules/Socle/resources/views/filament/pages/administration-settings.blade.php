<x-filament-panels::page>
    <div class="amb-settings-grid">
        @forelse ($this->displayedSections() as $section)
            <section id="{{ $section['id'] }}" class="amb-settings-section">
                <h2><x-filament::icon :icon="$section['icon']" />{{ __($section['label']) }}</h2>
                <ul>
                    @foreach ($section['links'] as $link)
                        <li>
                            @if ($link['url'])
                                <a href="{{ $link['url'] }}"><span>{{ __($link['label']) }}</span><x-filament::icon icon="heroicon-o-chevron-right" /></a>
                            @else
                                <div class="amb-settings-unavailable"><span>{{ __($link['label']) }}</span><small>{{ __('Pas encore disponible') }}</small></div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p>{{ __('Aucun paramètre accessible avec vos habilitations actuelles.') }}</p>
        @endforelse
    </div>
</x-filament-panels::page>
