@props([
    'type' => 'button',
    'variant' => 'primary',
    'loadingText' => 'Chargement...',
    'target' => null,
])

@php
    $baseClasses = "inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg font-medium transition-all duration-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-70 disabled:cursor-not-allowed";
    $variants = [
        'primary' => 'bg-blue-600 hover:bg-blue-700 text-white border-transparent',
        'secondary' => 'bg-slate-800 hover:bg-slate-900 text-white border-transparent',
        'danger' => 'bg-red-600 hover:bg-red-700 text-white border-transparent',
        'success' => 'bg-emerald-600 hover:bg-emerald-700 text-white border-transparent',
        'outline' => 'bg-transparent border-slate-300 hover:bg-slate-100 text-slate-700 dark:text-slate-200 dark:border-slate-700 dark:hover:bg-slate-800',
    ];
    $variantClass = $variants[$variant] ?? $variants['primary'];
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => "$baseClasses $variantClass"]) }}
    @if($target) wire:loading.attr="disabled" wire:target="{{ $target }}" @else wire:loading.attr="disabled" @endif
>
    <span @if($target) wire:loading.remove wire:target="{{ $target }}" @else wire:loading.remove @endif>
        {{ $slot }}
    </span>

    <span class="inline-flex items-center gap-2" @if($target) wire:loading wire:target="{{ $target }}" @else wire:loading @endif>
        <x-message-loading size="20" class="text-current" />
        <span>{{ $loadingText }}</span>
    </span>
</button>
