@php
    $record = $getRecord();
    $photo = $record->eleve?->photo
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($record->eleve->photo)
        : null;
    $statut = match ($record->statut) {
        'en_attente_versement' => 'En attente de versement',
        'active' => 'Active',
        'annulee' => 'Annulée',
        default => (string) $record->statut,
    };
@endphp
<a href="{{ \App\Modules\Scolarite\Filament\Resources\InscriptionResource::getUrl('view', ['record' => $record]) }}" class="block">
    <x-ambassadors-card
        :image="$photo"
        icon="heroicon-o-user"
        :label="$record->classe?->nom ?: 'Classe non affectée'"
        :title="trim(($record->eleve?->prenom ?? '').' '.($record->eleve?->nom ?? ''))"
        :badge="$statut"
    >Inscrit le {{ optional($record->date_inscription)->format('d/m/Y') }}</x-ambassadors-card>
</a>
