<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Models\SeuilValidation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeuilValidationResource extends Resource
{
    protected static ?string $model = SeuilValidation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflows & validations';

    protected static ?string $modelLabel = 'Seuil de validation';

    protected static ?string $pluralModelLabel = 'Seuils de validation';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('categorie_depense_id')->label('Identifiant de la catégorie de dépense')->numeric()->minValue(1)->required(),
            TextInput::make('montant_min')->label('Montant minimum')->numeric()->minValue(0)->default(0)->required(),
            TextInput::make('montant_max')->label('Montant maximum')->numeric()->minValue(0)->helperText('Laisser vide pour un plafond illimité.'),
            Select::make('role_validateur_requis')->label('Rôle validateur')->options([
                'Fondateur' => 'Fondateur', 'Administrateur' => 'Administrateur', 'Comptable' => 'Comptable',
            ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('categorie_depense_id')->label('Catégorie')->sortable(),
            TextColumn::make('montant_min')->label('Minimum')->money('XAF')->sortable(),
            TextColumn::make('montant_max')->label('Maximum')->money('XAF')->placeholder('Illimité')->sortable(),
            TextColumn::make('role_validateur_requis')->label('Validateur')->badge(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => SeuilValidationResource\Pages\ListSeuilsValidation::route('/'), 'create' => SeuilValidationResource\Pages\CreateSeuilValidation::route('/create'), 'edit' => SeuilValidationResource\Pages\EditSeuilValidation::route('/{record}/edit')];
    }
}
