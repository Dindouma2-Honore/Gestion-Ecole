<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources;

use App\Modules\VieScolaire\Models\Reclamation;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ReclamationResource extends Resource
{
    protected static ?string $model = Reclamation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|UnitEnum|null $navigationGroup = 'Vie scolaire';

    protected static ?string $navigationLabel = 'Réclamations & incidents';

    protected static ?string $modelLabel = 'réclamation';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Type')
                    ->options([
                        'reclamation_parent' => 'Réclamation parent',
                        'incident_scolaire' => 'Incident scolaire',
                        'plainte' => 'Plainte',
                    ])
                    ->required(),

                TextInput::make('categorie')->label('Catégorie')->maxLength(100),

                Textarea::make('description')
                    ->label('Description')
                    ->required()
                    ->columnSpanFull(),

                Select::make('priorite')
                    ->label('Priorité')
                    ->options([
                        'basse' => 'Basse', 'normale' => 'Normale',
                        'haute' => 'Haute', 'urgente' => 'Urgente',
                    ])
                    ->default('normale')
                    ->required(),

                TextInput::make('responsable_id')
                    ->label('ID responsable')
                    ->numeric()
                    ->helperText('Renseigné automatiquement à l\'affectation via ReclamationServiceInterface::affecter().'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('categorie')->label('Catégorie')->placeholder('—'),
                Tables\Columns\TextColumn::make('description')->label('Description')->limit(50),
                Tables\Columns\TextColumn::make('priorite')
                    ->label('Priorité')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'urgente' => 'danger',
                        'haute' => 'warning',
                        'basse' => 'gray',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('statut')->label('Statut')->badge(),
                Tables\Columns\TextColumn::make('delai_reponse')->label('Délai')->date(),
            ])
            ->filters([
                SelectFilter::make('statut')->options([
                    'ouverte' => 'Ouverte', 'affectee' => 'Affectée',
                    'en_cours' => 'En cours', 'resolue' => 'Résolue', 'cloturee' => 'Clôturée',
                ]),
                SelectFilter::make('priorite')->options([
                    'basse' => 'Basse', 'normale' => 'Normale',
                    'haute' => 'Haute', 'urgente' => 'Urgente',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ReclamationResource\Pages\ListReclamations::route('/'),
            'create' => ReclamationResource\Pages\CreateReclamation::route('/create'),
            'edit' => ReclamationResource\Pages\EditReclamation::route('/{record}/edit'),
        ];
    }
}
