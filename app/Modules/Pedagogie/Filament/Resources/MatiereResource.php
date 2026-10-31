<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages;
use App\Modules\Pedagogie\Filament\Support\OrganisationResource;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Services\CatalogueMatiereService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MatiereResource extends OrganisationResource
{
    protected static bool $isDiscovered = true;

    protected static ?string $model = Matiere::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Matières & domaines';

    protected static ?string $modelLabel = 'matière';

    protected static ?string $pluralModelLabel = 'matières';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')
                    ->label('Nom')
                    ->required()
                    ->maxLength(150),
                Select::make('categorie_matiere_id')
                    ->label('Catégorie')
                    ->relationship('categorie', 'nom')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('code')
                    ->label('Code')
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->helperText('Ex: MATH, FR, ANG... — vide : code généré automatiquement'),

                TextInput::make('coefficient_defaut')
                    ->label('Coefficient par défaut')
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(999.99)
                    ->default(1),

                Select::make('nature')->label('Nature')->options(['matiere' => 'Matière', 'domaine' => 'Domaine', 'activite' => 'Activité'])->default('matiere')->required(),

                Textarea::make('description')
                    ->label('Description'),

                Toggle::make('actif')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::organisationTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('nom')
                    ->label('Matière')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('categorie.nom')
                    ->label('Catégorie')
                    ->badge(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable(),

                Tables\Columns\TextColumn::make('nature')->label('Nature')->badge()->formatStateUsing(fn ($state) => ['matiere' => 'Matière', 'domaine' => 'Domaine', 'activite' => 'Activité'][$state] ?? $state),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Statut')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function enregistrer(array $data, ?Model $record = null): Model
    {
        return app(CatalogueMatiereService::class)->enregistrer($data, $record?->getKey());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMatieres::route('/'),
            'edit' => Pages\EditMatiere::route('/{record}/edit'),
        ];
    }
}
