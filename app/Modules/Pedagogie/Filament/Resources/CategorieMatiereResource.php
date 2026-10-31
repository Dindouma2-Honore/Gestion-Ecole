<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Filament\Support\OrganisationResource;
use App\Modules\Pedagogie\Models\CategorieMatiere;
use App\Modules\Pedagogie\Services\CatalogueMatiereService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CategorieMatiereResource extends OrganisationResource
{
    protected static bool $isDiscovered = true;

    protected static ?string $model = CategorieMatiere::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $modelLabel = 'Catégorie de matière';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom')->required()->maxLength(100),
            Textarea::make('description')->label('Description'),
            Toggle::make('actif')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::organisationTable($table)->columns([
            Tables\Columns\TextColumn::make('code')->label('Code')->searchable(),
            Tables\Columns\TextColumn::make('nom')->label('Nom')->searchable(),
            Tables\Columns\IconColumn::make('actif')->label('Statut')->boolean(),
        ]);
    }

    public static function enregistrer(array $data, ?Model $record = null): Model
    {
        return app(CatalogueMatiereService::class)->enregistrerCategorie($data, $record?->getKey());
    }

    public static function getPages(): array
    {
        return ['index' => CategorieMatiereResource\Pages\ListCategoriesMatieres::route('/'), 'edit' => CategorieMatiereResource\Pages\EditCategorieMatiere::route('/{record}/edit')];
    }
}
