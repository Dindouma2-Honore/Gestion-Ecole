<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Filament\Support\NiveauScopeSelect;
use App\Modules\Logistique\Filament\Resources\LivreResource\Pages;
use App\Modules\Logistique\Models\Livre;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class LivreResource extends Resource
{
    protected static ?string $model = Livre::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Bibliothèque';

    protected static ?string $modelLabel = 'livre';

    /**
     * L'interface d'emprunt/retour rapide type comptoir (avec scan de
     * code-barres) mentionnée dans la doc H.62 mérite une Page Filament
     * personnalisée dédiée plutôt qu'un simple formulaire de Resource — à
     * construire une fois le matériel de scan confirmé avec Joel.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('titre')->label('Titre')->required()->maxLength(255),
                TextInput::make('auteur')->label('Auteur')->maxLength(255),
                TextInput::make('isbn')->label('ISBN')->maxLength(20),
                TextInput::make('categorie')->label('Catégorie')->maxLength(100),
                NiveauScopeSelect::make(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('titre')->label('Titre')->searchable(),
                Tables\Columns\TextColumn::make('auteur')->label('Auteur')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('categorie')->label('Catégorie')->placeholder('—'),
                Tables\Columns\TextColumn::make('exemplaires_disponibles_count')
                    ->label('Exemplaires disponibles')
                    ->state(fn (Livre $record) => $record->exemplaires()->where('disponible', true)->count()),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLivres::route('/'),
            'create' => Pages\CreateLivre::route('/create'),
            'edit' => Pages\EditLivre::route('/{record}/edit'),
        ];
    }
}
