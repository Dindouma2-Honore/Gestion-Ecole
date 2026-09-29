<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Modules\Scolarite\Contracts\FraisServiceContract;
use App\Modules\Scolarite\Exceptions\CategorieFraisEnUsageException;
use App\Modules\Scolarite\Filament\Resources\CategorieFraisResource\Pages;
use App\Modules\Scolarite\Models\CategorieFrais;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

/**
 * CRUD libre : aucune catégorie n'est imposée par le système (Module 5
 * §1) — contrairement à une éventuelle règle "2 groupes fixes".
 */
class CategorieFraisResource extends Resource
{
    protected static ?string $model = CategorieFrais::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Catégories de frais';

    protected static ?string $modelLabel = 'catégorie de frais';

    protected static ?string $pluralModelLabel = 'catégories de frais';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom')->required()->maxLength(100)->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nom')->label('Nom')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('frais_sum_montant')
                    ->label('Frais rattachés')
                    ->sum('frais', 'montant')
                    ->money('XAF'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(function (CategorieFrais $record) {
                        try {
                            app(FraisServiceContract::class)->supprimerCategorie($record->id);
                        } catch (CategorieFraisEnUsageException $e) {
                            Notification::make()->title('Suppression impossible')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategoriesFrais::route('/'),
            'create' => Pages\CreateCategorieFrais::route('/create'),
            'edit' => Pages\EditCategorieFrais::route('/{record}/edit'),
        ];
    }
}
