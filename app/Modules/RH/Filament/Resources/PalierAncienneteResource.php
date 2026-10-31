<?php

namespace App\Modules\RH\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Resources\PalierAncienneteResource\Pages;
use App\Modules\RH\Filament\Support\RhActions;
use App\Modules\RH\Models\PalierAnciennete;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PalierAncienneteResource extends Resource
{
    protected static ?string $model = PalierAnciennete::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'palier d’ancienneté';

    protected static ?string $pluralModelLabel = 'Paliers d’ancienneté';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make('Palier d’ancienneté')->description('Réservé à la progression automatique et à la grille salariale.')->columns(2)->schema([
            TextInput::make('nom')->required()->unique(ignoreRecord: true), Toggle::make('actif')->default(true),
            TextInput::make('anciennete_min_mois')->label('Minimum (mois)')->numeric()->minValue(0)->required(),
            TextInput::make('anciennete_max_mois')->label('Maximum (mois)')->numeric()->minValue(0)->gte('anciennete_min_mois')->helperText('Vide pour le dernier palier.'),
            Textarea::make('description')->columnSpanFull(),
        ])]);
    }

    public static function table(Table $table): Table
    {
        AmbassadorsDesign::configureTable($table, null);

        return $table->columns([
            TextColumn::make('nom')->searchable()->sortable(), TextColumn::make('anciennete_min_mois')->label('Minimum')->suffix(' mois'),
            TextColumn::make('anciennete_max_mois')->label('Maximum')->formatStateUsing(fn ($state) => $state === null ? 'Sans limite' : $state.' mois'), IconColumn::make('actif')->boolean(),
        ])->actions([RhActions::edit(), RhActions::delete()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPaliersAnciennete::route('/'), 'create' => Pages\CreatePalierAnciennete::route('/create'), 'edit' => Pages\EditPalierAnciennete::route('/{record}/edit')];
    }
}
