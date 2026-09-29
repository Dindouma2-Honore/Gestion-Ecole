<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class MatiereResource extends Resource
{
    protected static ?string $model = Matiere::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Matières';

    protected static ?string $modelLabel = 'matière';

    protected static ?string $pluralModelLabel = 'matières';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('categorie_matiere_id')
                    ->label('Catégorie')
                    ->relationship('categorie', 'nom')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('nom')
                    ->label('Nom')
                    ->required()
                    ->maxLength(150),

                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->helperText('Ex: MATH, FR, ANG...'),

                Select::make('niveau_id')
                    ->label('Niveau (optionnel)')
                    ->options(fn (): array => collect(app(ParametrageServiceContract::class)->getTousLesNiveaux())->pluck('nom', 'id')->all())
                    ->searchable(),

                TextInput::make('coefficient_defaut')
                    ->label('Coefficient par défaut')
                    ->numeric()
                    ->default(1)
                    ->required(),

                Textarea::make('description')
                    ->label('Description'),

                Toggle::make('actif')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nom')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('categorie.nom')
                    ->label('Catégorie')
                    ->badge(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable(),

                Tables\Columns\TextColumn::make('coefficient_defaut')->label('Coefficient'),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMatieres::route('/'),
            'create' => Pages\CreateMatiere::route('/create'),
            'edit' => Pages\EditMatiere::route('/{record}/edit'),
        ];
    }
}
