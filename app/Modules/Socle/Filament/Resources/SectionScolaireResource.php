<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Models\SectionScolaire;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SectionScolaireResource extends Resource
{
    protected static ?string $model = SectionScolaire::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-language';

    protected static string|\UnitEnum|null $navigationGroup = 'Structure de l’établissement';

    protected static ?string $modelLabel = 'Section scolaire';

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
        return ['index' => SectionScolaireResource\Pages\ListSectionsScolaires::route('/'), 'create' => SectionScolaireResource\Pages\CreateSectionScolaire::route('/create'), 'edit' => SectionScolaireResource\Pages\EditSectionScolaire::route('/{record}/edit')];
    }
}
