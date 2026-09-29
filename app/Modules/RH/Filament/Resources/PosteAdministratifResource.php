<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Filament\Resources\PosteAdministratifResource\Pages;
use App\Modules\RH\Models\PosteAdministratif;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PosteAdministratifResource extends Resource
{
    protected static ?string $model = PosteAdministratif::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'fonction';

    protected static ?string $pluralModelLabel = 'fonctions';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom de la fonction')->required()->maxLength(255)->unique(ignoreRecord: true),
            Textarea::make('description')->label('Description')->columnSpanFull(),
            Toggle::make('actif')->label('Fonction active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('nom')->label('Fonction')->searchable()->sortable(),
            TextColumn::make('employes_count')->counts('employes')->label('Personnel associé')->sortable(),
            IconColumn::make('actif')->label('Active')->boolean(),
            TextColumn::make('created_at')->label('Créée le')->dateTime('d/m/Y')->sortable(),
        ])->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPostesAdministratifs::route('/'),
            'create' => Pages\CreatePosteAdministratif::route('/create'),
            'edit' => Pages\EditPosteAdministratif::route('/{record}/edit'),
        ];
    }
}
