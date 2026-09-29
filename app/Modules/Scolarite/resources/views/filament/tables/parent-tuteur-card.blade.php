@php
    $record = $getRecord();
    $enfants = (int) ($record->eleves_count ?? 0);
@endphp
<a href="{{ \App\Modules\Scolarite\Filament\Resources\ParentTuteurResource::getUrl('edit', ['record' => $record]) }}" class="block">
    <x-ambassadors-card
        icon="heroicon-o-users"
        :label="$record->profession ?: 'Parent / tuteur'"
        :title="trim($record->prenom.' '.$record->nom)"
        :badge="$record->portail_actif ? 'Portail actif' : 'Portail inactif'"
    >{{ $enfants }} enfant(s) · {{ $record->telephone ?: $record->email ?: 'Contact non renseigné' }}</x-ambassadors-card>
</a>
