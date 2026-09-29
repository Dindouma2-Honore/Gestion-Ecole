@php
    /** @var \App\Modules\Scolarite\Models\Eleve $record */
    $record = $getRecord();
    $photo = $record->photo
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($record->photo)
        : null;
    $statut = match ($record->statut) {
        'prospect' => 'Prospect',
        'candidat' => 'Candidat',
        'admis' => 'Admis',
        'inscrit' => 'Inscrit',
        'actif' => 'Actif',
        'suspendu' => 'Suspendu',
        'retire' => 'Retiré',
        'diplome' => 'Diplômé',
        'archive' => 'Archivé',
        default => (string) $record->statut,
    };
    $titre = trim($record->prenom.' '.$record->nom);
    $sexeLabel = $record->sexe === 'F' ? 'Fille' : ($record->sexe === 'M' ? 'Garçon' : '—');
@endphp
<a href="{{ \App\Modules\Scolarite\Filament\Resources\EleveResource::getUrl('edit', ['record' => $record]) }}" class="block">
    <x-ambassadors-card
        :image="$photo"
        icon="heroicon-o-user"
        :label="$record->matricule_permanent ?: 'Sans matricule'"
        :title="$titre"
        :badge="$statut"
    >{{ $sexeLabel }} · Créé le {{ optional($record->created_at)->format('d/m/Y') }}</x-ambassadors-card>
</a>