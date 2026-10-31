<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Filament\Resources;

use App\Modules\Assiduite\Filament\Resources\AssiduiteJournaliereResource\Pages;
use App\Modules\Assiduite\Models\AssiduiteJournaliere;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class AssiduiteJournaliereResource extends Resource
{
    protected static ?string $model = AssiduiteJournaliere::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'assiduité journalière';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('date_assiduite')->label('Date')->date('d/m/Y')->sortable(),
            Tables\Columns\TextColumn::make('personne_type')->label('Type')->badge(),
            Tables\Columns\TextColumn::make('personne_id')->label('Personne')->formatStateUsing(fn ($state, $record) => self::nomPersonne($record->personne_type, (int) $state))->searchable(),
            Tables\Columns\TextColumn::make('heure_arrivee')->label('Entrée')->placeholder('—'), Tables\Columns\TextColumn::make('heure_depart')->label('Sortie')->placeholder('—'), Tables\Columns\TextColumn::make('minutes_retard')->label('Retard')->suffix(' min'),
            Tables\Columns\TextColumn::make('statut')->label('Statut')->badge()->formatStateUsing(fn ($state) => match ($state) {
                'present' => 'Présent','retard' => 'En retard','a_verifier' => 'À vérifier','absent_presume' => 'Absent présumé','absent_confirme' => 'Absent confirmé','absence_justifiee' => 'Absence justifiée','depart_anticipe' => 'Départ anticipé','conge' => 'Congé','mission' => 'Mission','non_attendu' => 'Non attendu',default => $state
            }),
            Tables\Columns\TextColumn::make('source')->label('Source')->badge(),
        ])->filters([SelectFilter::make('personne_type')->label('Type')->options(['eleve' => 'Élève', 'personnel' => 'Personnel']), SelectFilter::make('statut')->options(['present' => 'Présent', 'retard' => 'En retard', 'a_verifier' => 'À vérifier', 'absent_presume' => 'Absent présumé', 'absent_confirme' => 'Absent confirmé', 'absence_justifiee' => 'Justifiée', 'depart_anticipe' => 'Départ anticipé', 'conge' => 'Congé', 'mission' => 'Mission', 'non_attendu' => 'Non attendu'])])->defaultSort('date_assiduite', 'desc')->defaultPaginationPageOption(5);
    }

    private static function nomPersonne(string $type, int $id): string
    {
        if ($type === 'eleve') {
            $e = DB::table('eleves')->find($id);

            return $e ? trim("{$e->nom} {$e->prenom}")." — {$e->matricule_permanent}" : "Élève #{$id}";
        } $e = DB::table('employes')->find($id);

        return $e ? trim("{$e->nom} {$e->prenom}") : "Personnel #{$id}";
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAssiduites::route('/')];
    }
}
