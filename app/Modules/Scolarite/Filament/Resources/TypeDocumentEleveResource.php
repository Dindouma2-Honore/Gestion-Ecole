<?php

namespace App\Modules\Scolarite\Filament\Resources;

use App\Modules\Scolarite\Models\TypeDocumentEleve;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TypeDocumentEleveResource extends Resource
{
    protected static ?string $model = TypeDocumentEleve::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-clip';

    protected static string|\UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $modelLabel = 'Type de document élève';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Fondateur', 'Directeur']) ?? false;
    }

    public static function form(Schema $s): Schema
    {
        return $s->components([TextInput::make('code')->required()->unique(ignoreRecord: true), TextInput::make('nom')->required(), Toggle::make('obligatoire'), Toggle::make('actif')->default(true)]);
    }

    public static function table(Table $t): Table
    {
        return $t->columns([TextColumn::make('code')->searchable(), TextColumn::make('nom')->searchable(), IconColumn::make('obligatoire')->boolean(), IconColumn::make('actif')->boolean()]);
    }

    public static function getPages(): array
    {
        return ['index' => TypeDocumentEleveResource\Pages\ListTypesDocumentsEleve::route('/'), 'create' => TypeDocumentEleveResource\Pages\CreateTypeDocumentEleve::route('/create'), 'edit' => TypeDocumentEleveResource\Pages\EditTypeDocumentEleve::route('/{record}/edit')];
    }
}
