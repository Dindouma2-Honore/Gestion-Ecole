<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Filament\Resources\CourrierModeleResource\Pages;
use App\Modules\Socle\Models\CourrierModele;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CourrierModeleResource extends Resource
{
    protected static ?string $model = CourrierModele::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';
    protected static string|\UnitEnum|null $navigationGroup = 'Module Complémentaire';
    protected static ?string $navigationLabel = 'Modèles de courriers';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->required(),
            TextInput::make('objet')->required()->helperText('Variables acceptées : {nom}, {date}, etc.'),
            Textarea::make('contenu')->required()->rows(12)->columnSpanFull(),
            TagsInput::make('variables')->helperText('Noms sans accolades, par exemple nom, date.'),
            Toggle::make('actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('nom')->searchable(),
            TextColumn::make('objet')->searchable(),
            IconColumn::make('actif')->boolean(),
        ])->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCourrierModeles::route('/'), 'create' => Pages\CreateCourrierModele::route('/create'), 'edit' => Pages\EditCourrierModele::route('/{record}/edit')];
    }
}
