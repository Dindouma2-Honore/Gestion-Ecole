@php($record = $getRecord())
<a href="{{ \App\Modules\RH\Filament\Resources\EnseignantResource::getUrl('edit', ['record' => $record]) }}" class="block">
    <x-ambassadors-card
        icon="heroicon-o-academic-cap"
        :label="$record->specialite ?: 'Spécialité non renseignée'"
        :title="$record->employe?->nom_complet ?: 'Enseignant sans employé associé'"
        :badge="ucfirst((string) $record->statut_contractuel)"
    >Charge hebdomadaire : {{ $record->charge_horaire_hebdo }} h</x-ambassadors-card>
</a>
