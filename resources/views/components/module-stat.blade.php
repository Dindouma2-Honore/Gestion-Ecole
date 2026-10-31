@props(['label', 'value', 'icon' => 'heroicon-o-chart-bar', 'description' => null, 'url' => null, 'color' => null])
<article class="amb-admin-stat amb-module-stat">
    <div @class(['amb-admin-stat__icon', 'text-success-600' => $color === 'success', 'text-danger-600' => $color === 'danger', 'text-warning-600' => $color === 'warning'])><x-filament::icon :icon="$icon" /></div>
    <div>
        <h2>@if ($url && $url !== '#')<a href="{{ $url }}">{{ $label }}</a>@else{{ $label }}@endif</h2>
        <strong>{{ $value }}</strong>
        @if ($description)<p>{{ $description }}</p>@endif
    </div>
</article>
