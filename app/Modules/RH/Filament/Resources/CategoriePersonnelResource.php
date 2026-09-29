<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Filament\Resources\CategoriePersonnelResource\Pages;
use App\Modules\RH\Models\CategoriePersonnel;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriePersonnelResource extends Resource
{
    protected static ?string $model = CategoriePersonnel::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'catégorie de personnel';

    protected static ?string $pluralModelLabel = 'catégories de personnel';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom de la catégorie')->required()->maxLength(255)->unique(ignoreRecord: true),
            Textarea::make('description')->label('Description')->columnSpanFull(),
            Toggle::make('progression_automatique')->label('Catégorie d’ancienneté automatique')->default(false)->live(),
            TextInput::make('anciennete_min_mois')->label('Ancienneté minimale (mois)')->numeric()->minValue(0)->visible(fn (Get $get): bool => (bool) $get('progression_automatique'))->required(fn (Get $get): bool => (bool) $get('progression_automatique')),
            TextInput::make('anciennete_max_mois')->label('Ancienneté maximale (mois)')->numeric()->minValue(0)->visible(fn (Get $get): bool => (bool) $get('progression_automatique'))->helperText('Laissez vide pour la dernière catégorie.'),
            Toggle::make('actif')->label('Catégorie active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('nom')->label('Catégorie')->searchable()->sortable(),
            TextColumn::make('description')->label('Description')->limit(60),
            TextColumn::make('anciennete_min_mois')->label('Minimum')->suffix(' mois')->sortable(),
            TextColumn::make('anciennete_max_mois')->label('Maximum')->formatStateUsing(fn ($state) => $state === null ? 'Sans limite' : $state.' mois'),
            TextColumn::make('employes_count')->counts('employes')->label('Personnel associé')->sortable(),
            IconColumn::make('actif')->label('Active')->boolean(),
            TextColumn::make('created_at')->label('Créée le')->dateTime('d/m/Y')->sortable(),
        ])->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategoriesPersonnel::route('/'),
            'create' => Pages\CreateCategoriePersonnel::route('/create'),
            'edit' => Pages\EditCategoriePersonnel::route('/{record}/edit'),
        ];
    }
}
