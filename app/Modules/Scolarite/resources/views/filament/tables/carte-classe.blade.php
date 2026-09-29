@php
    /** @var \App\Modules\Scolarite\Models\Classe $record */
    $record = $getRecord();
    $effectif = $record->inscriptions()->whereIn('statut', ['en_attente_versement', 'active'])->count();
    $label = 'Niveau #'.$record->niveau_id.' · Année #'.$record->annee_scolaire_id;
    $badge = $effectif.'/'.$record->capacite_max;
@endphp
<a href="{{ \App\Modules\Scolarite\Filament\Resources\ClasseResource::getUrl('edit', ['record' => $record]) }}" class="block">
    <x-ambassadors-card
        icon="heroicon-o-building-library"
        :label="$label"
        :title="$record->nom"
        :badge="$badge"
    >Capacité maximale : {{ $record->capacite_max }} élèves</x-ambassadors-card>
</a>