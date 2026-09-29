@php
    $record = $getRecord();
    $photo = $record->photo
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($record->photo)
        : null;
    $statut = ucfirst(str_replace('_', ' ', (string) $record->statut));
    $matricule = $record->matricule_permanent ?: 'Matricule en attente';
@endphp
<a href="{{ \App\Modules\Scolarite\Filament\Resources\EleveResource::getUrl('edit', ['record' => $record]) }}" class="block">
    <x-ambassadors-card
        :image="$photo"
        icon="heroicon-o-user"
        :label="$matricule"
        :title="trim($record->prenom.' '.$record->nom)"
        :badge="$statut"
    >{{ $record->sexe ?: 'Sexe non renseigné' }} · Né(e) le {{ optional($record->date_naissance)->format('d/m/Y') ?: '—' }}</x-ambassadors-card>
</a>
