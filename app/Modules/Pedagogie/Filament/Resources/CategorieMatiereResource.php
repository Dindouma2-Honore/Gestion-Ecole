<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Models\CategorieMatiere;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategorieMatiereResource extends Resource
{
    protected static ?string $model = CategorieMatiere::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $modelLabel = 'Catégorie de matière';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('nom')->required()->maxLength(100), TextInput::make('code')->required()->maxLength(30)->unique(ignoreRecord: true), Textarea::make('description'), TextInput::make('ordre_affichage')->numeric()->default(0), Toggle::make('actif')->default(true)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('code')->searchable(), TextColumn::make('nom')->searchable(), TextColumn::make('ordre_affichage')->label('Ordre')->sortable(), IconColumn::make('actif')->boolean()]);
    }

    public static function getPages(): array
    {
        return ['index' => CategorieMatiereResource\Pages\ListCategoriesMatieres::route('/'), 'create' => CategorieMatiereResource\Pages\CreateCategorieMatiere::route('/create'), 'edit' => CategorieMatiereResource\Pages\EditCategorieMatiere::route('/{record}/edit')];
    }
}
