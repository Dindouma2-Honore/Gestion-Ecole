<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Models\Filiere;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FiliereResource extends Resource
{
    protected static ?string $model = Filiere::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Structure de l’établissement';

    protected static ?string $modelLabel = 'Série / filière';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('nom')->required(), TextInput::make('code')->required()->unique(ignoreRecord: true), Textarea::make('description'), Toggle::make('actif')->default(true)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('code'), TextColumn::make('nom')->searchable(), IconColumn::make('actif')->boolean()])->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => FiliereResource\Pages\ListFilieres::route('/'), 'create' => FiliereResource\Pages\CreateFiliere::route('/create'), 'edit' => FiliereResource\Pages\EditFiliere::route('/{record}/edit')];
    }
}
