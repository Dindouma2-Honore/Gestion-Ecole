<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Models\Cycle;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CycleResource extends Resource
{
    protected static ?string $model = Cycle::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Structure de l’établissement';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('nom')->required(), TextInput::make('code')->required()->unique(ignoreRecord: true), TextInput::make('description'), TextInput::make('ordre')->numeric()->default(0), Toggle::make('actif')->default(true)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('ordre')->sortable(), TextColumn::make('code'), TextColumn::make('nom')->searchable(), IconColumn::make('actif')->boolean()])->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => CycleResource\Pages\ListCycles::route('/'), 'create' => CycleResource\Pages\CreateCycle::route('/create'), 'edit' => CycleResource\Pages\EditCycle::route('/{record}/edit')];
    }
}
