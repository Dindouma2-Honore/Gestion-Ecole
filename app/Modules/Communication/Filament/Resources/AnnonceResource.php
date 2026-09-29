<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Filament\Resources\AnnonceResource\Pages;
use App\Modules\Communication\Models\Annonce;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AnnonceResource extends Resource
{
    protected static ?string $model = Annonce::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|UnitEnum|null $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'Annonces';

    protected static ?string $modelLabel = 'annonce';

    protected static ?string $pluralModelLabel = 'annonces';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('titre')
                    ->required()
                    ->maxLength(255),

                Textarea::make('contenu')
                    ->required()
                    ->rows(5),

                Select::make('cible_type')
                    ->options([
                        'generale' => 'Générale (Tous)',
                        'classe' => 'Classe spécifique',
                        'niveau' => 'Niveau spécifique',
                        'personnel' => 'Personnel uniquement',
                    ])
                    ->default('generale')
                    ->required()
                    ->live(),

                Select::make('cible_id')
                    ->label('Cible spécifique')
                    ->options(function ($get): array {
                        if ($get('cible_type') === 'classe') {
                            return \Illuminate\Support\Facades\DB::table('classes')->pluck('nom', 'id')->toArray();
                        }
                        if ($get('cible_type') === 'niveau') {
                            return \Illuminate\Support\Facades\DB::table('niveaux')->pluck('nom', 'id')->toArray();
                        }

                        return [];
                    })
                    ->visible(fn ($get) => in_array($get('cible_type'), ['classe', 'niveau'])),

                DatePicker::make('date_publication')
                    ->default(now())
                    ->required(),

                DatePicker::make('date_expiration')
                    ->label("Date d'expiration"),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('titre')->searchable(),
                Tables\Columns\TextColumn::make('cible_type')->badge()->label('Cible'),
                Tables\Columns\TextColumn::make('auteur.name')->label('Publié par'),
                Tables\Columns\TextColumn::make('date_publication')->date()->sortable(),
                Tables\Columns\TextColumn::make('date_expiration')->date()->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnonces::route('/'),
            'create' => Pages\CreateAnnonce::route('/create'),
            'edit' => Pages\EditAnnonce::route('/{record}/edit'),
        ];
    }
}
