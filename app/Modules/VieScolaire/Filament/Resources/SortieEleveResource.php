<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources;

use App\Modules\VieScolaire\Models\SortieEleve;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class SortieEleveResource extends Resource
{
    protected static ?string $model = SortieEleve::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-right-start-on-rectangle';

    protected static string|UnitEnum|null $navigationGroup = 'Vie scolaire';

    protected static ?string $navigationLabel = 'Sorties élèves';

    protected static ?string $modelLabel = 'sortie élève';

    protected static ?string $pluralModelLabel = 'sorties élèves';

    /**
     * Formulaire simplifié pour la sortie normale — la vérification
     * d'autorisation vit dans SortieEleveServiceInterface, jamais
     * recalculée ici. Pour une sortie exceptionnelle (avec justificatif
     * et motif), passer par une action dédiée plutôt que ce formulaire.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('eleve_id')
                    ->label('ID élève')
                    ->numeric()
                    ->required()
                    ->helperText('En attendant un Select alimenté par EleveServiceInterface (Scolarité).'),

                TextInput::make('parent_id')
                    ->label('ID parent (sortie normale)')
                    ->numeric()
                    ->helperText('Laisser vide pour une sortie exceptionnelle.'),

                TextInput::make('personne_autorisee_nom')
                    ->label('Nom (sortie exceptionnelle)')
                    ->maxLength(255),

                Select::make('type')
                    ->label('Type')
                    ->options(['normale' => 'Normale', 'exceptionnelle' => 'Exceptionnelle'])
                    ->required(),

                DateTimePicker::make('heure_sortie')
                    ->label('Heure de sortie')
                    ->default(now())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('eleve_id')->label('Élève (ID)'),
                Tables\Columns\TextColumn::make('type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('personne_autorisee_nom')->label('Personne')->placeholder('—'),
                Tables\Columns\TextColumn::make('heure_sortie')->label('Heure')->dateTime(),
                Tables\Columns\IconColumn::make('justificatif_document_id')
                    ->label('Justificatif')
                    ->boolean()
                    ->getStateUsing(fn (SortieEleve $record) => $record->justificatif_document_id !== null),
            ])
            ->defaultSort('heure_sortie', 'desc')
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => SortieEleveResource\Pages\ListSortiesEleves::route('/'),
            'create' => SortieEleveResource\Pages\CreateSortieEleve::route('/create'),
            'edit' => SortieEleveResource\Pages\EditSortieEleve::route('/{record}/edit'),
        ];
    }
}
