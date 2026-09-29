@php
    /** @var \App\Modules\Scolarite\Models\ParentTuteur $record */
    $record = $getRecord();
    $enfants = $record->eleves()->count();
    $titre = trim($record->prenom.' '.$record->nom);
    $label = $record->telephone ?: ($record->email ?: 'Aucun contact');
    $badge = $record->portail_actif ? 'Portail actif' : 'Portail inactif';
@endphp
<a href="{{ \App\Modules\Scolarite\Filament\Resources\ParentTuteurResource::getUrl('edit', ['record' => $record]) }}" class="block">
    <x-ambassadors-card
        icon="heroicon-o-users"
        :label="$label"
        :title="$titre"
        :badge="$badge"
    >{{ $enfants }} {{ $enfants > 1 ? 'enfants' : 'enfant' }} rattaché{{ $enfants > 1 ? 's' : '' }} · {{ $record->email ?: 'sans e-mail' }}</x-ambassadors-card>
</a>