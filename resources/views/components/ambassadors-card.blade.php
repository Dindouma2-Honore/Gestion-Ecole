@props(['image' => null, 'icon' => null, 'label', 'title', 'badge' => null, 'selected' => false, 'action' => 'Voir la fiche'])
@once
<style>
.amb-card{position:relative;display:flex;flex-direction:column;align-items:center;width:100%;height:100%;min-height:16rem;padding:1.5rem 1.15rem 1.15rem;overflow:hidden;border:1px solid rgba(7,22,63,.08);border-radius:1rem;background:#ffffff;box-shadow:0 8px 24px rgba(7,22,63,.1),0 2px 6px rgba(7,22,63,.06);color:#0f172a;text-align:center;isolation:isolate;transition:transform .22s,border-color .22s,box-shadow .22s}.amb-card:hover{transform:translateY(-3px);border-color:#d7aa35;box-shadow:0 18px 38px rgba(7,22,63,.16),0 0 0 2px rgba(215,170,53,.25)}.amb-card.is-selected{border-color:#d7aa35;box-shadow:0 0 0 3px rgba(215,170,53,.28),0 18px 38px rgba(7,22,63,.18)}
.amb-card__avatar{position:relative;flex-shrink:0;width:6rem;height:6rem;margin-bottom:.9rem;overflow:hidden;border-radius:999px;border:2px solid #f4cf67;box-shadow:0 6px 14px rgba(7,22,63,.18)}
.amb-card__avatar img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.amb-card__avatar .amb-card__fallback{position:absolute;inset:0;display:grid;place-items:center;background:radial-gradient(circle at 65% 30%,#1948bd,#07163f 65%)}
.amb-card__avatar .amb-card__fallback svg{width:2.5rem;height:2.5rem;color:#f4cf67}
.amb-card__badge{position:absolute;top:1rem;right:1rem;z-index:2;border:1px solid rgba(7,22,63,.12);border-radius:999px;background:#eef3ff;padding:.35rem .65rem;color:#102f78;font-size:.68rem;font-weight:800;box-shadow:0 2px 6px rgba(7,22,63,.08)}
.amb-card__label{display:block;margin-bottom:.3rem;color:#b8860f;font-size:.68rem;font-weight:900;letter-spacing:.13em;text-transform:uppercase}
.amb-card__title{display:block;color:#0f172a;font-size:1.1rem;font-weight:850;line-height:1.2}
.amb-card__extra{display:block;margin-top:.5rem;color:#52617e;font-size:.76rem;line-height:1.4}
@media(max-width:650px){.amb-card{min-height:14rem}}
</style>
@endonce
<div {{ $attributes->class(['amb-card', 'is-selected' => $selected]) }}>
    @if($badge)<span class="amb-card__badge">{{ $badge }}</span>@endif
    <span class="amb-card__avatar">
        @if($image)<img src="{{ $image }}" alt="">@else<span class="amb-card__fallback"><x-filament::icon :icon="$icon ?: 'heroicon-o-photo'" /></span>@endif
    </span>
    <span class="amb-card__label">{{ $label }}</span>
    <strong class="amb-card__title">{{ $title }}</strong>
    @if(trim((string) $slot) !== '')<span class="amb-card__extra">{{ $slot }}</span>@endif
</div>

