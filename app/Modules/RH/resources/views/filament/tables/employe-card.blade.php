@php
    /** @var \App\Modules\RH\Models\Employe $record */
    $record = $getRecord();
    $photo = $record->photo
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($record->photo)
        : null;
    $statut = match ($record->statut) {
        'actif' => 'Actif',
        'suspendu' => 'Suspendu',
        'en_conge' => 'En congé',
        'demissionne' => 'Démissionné',
        'licencie' => 'Licencié',
        default => (string) $record->statut,
    };
    $titre = trim($record->prenom.' '.$record->nom);
@endphp
<a href="{{ \App\Modules\RH\Filament\Resources\EmployeResource::getUrl('view', ['record' => $record]) }}" class="block">
    <x-ambassadors-card
        :image="$photo"
        icon="heroicon-o-identification"
        :label="$record->matricule ?: 'Sans matricule'"
        :title="$titre"
        :badge="$statut"
    >{{ $record->poste ?: 'Poste non renseigné' }} · {{ $record->departement ?: 'Département non renseigné' }}</x-ambassadors-card>
</a>
