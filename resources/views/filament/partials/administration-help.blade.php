@if ($help)
    <details class="amb-menu-help">
        <summary>
            <x-filament::icon icon="heroicon-o-information-circle" />
            <span>{{ __('Comment utiliser ce menu ?') }}</span>
        </summary>
        <div class="amb-menu-help__text">
            <strong>{{ __($help['title']) }}</strong>
            <p>{{ __($help['text']) }}</p>
        </div>
    </details>
@endif
