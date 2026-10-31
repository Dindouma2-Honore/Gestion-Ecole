@php
    $record = $getRecord();
    $photo = $record->eleve?->photo
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($record->eleve->photo)
        : null;
    $statut = match ($record->statut) {
        'en_attente_versement' => 'En attente de versement',
        'validee' => 'Validée',
        'active' => 'Active',
        'annulee' => 'Annulée',
        default => (string) $record->statut,
    };

    $parentNom = $record->parent_principal_nom;
    $parentTel = $record->parent_principal_telephone;

    $fraisDivers = $record->frais_divers_souscrits ?? [];
    $fraisDiversNoms = collect($fraisDivers)->pluck('nom')->implode(', ');
@endphp
<a href="{{ \App\Modules\Scolarite\Filament\Resources\InscriptionResource::getUrl('view', ['record' => $record]) }}" class="block">
    <x-ambassadors-card
        :image="$photo"
        icon="heroicon-o-user"
        :label="$record->classe?->nom ?: 'Classe non affectée'"
        :title="trim(($record->eleve?->prenom ?? '').' '.($record->eleve?->nom ?? ''))"
        :badge="$statut"
    >
        <span class="block">Inscrit le {{ optional($record->date_inscription)->format('d/m/Y') }}</span>
        @if($parentNom)
            <span class="block mt-1 font-semibold text-slate-700">
                Parent : {{ $parentNom }}@if($parentTel) <span class="text-slate-500 font-normal">({{ $parentTel }})</span>@endif
            </span>
        @endif
        @if($fraisDiversNoms !== '')
            <span class="block mt-1 text-amber-700 font-semibold" title="{{ $fraisDiversNoms }}">
                Frais divers : {{ $fraisDiversNoms }}
            </span>
        @endif
    </x-ambassadors-card>
</a>
