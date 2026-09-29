<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Models\FormatNumerotation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FormatNumerotationResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = FormatNumerotation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hashtag';

    protected static string|\UnitEnum|null $navigationGroup = 'Structure de l’établissement';

    protected static ?string $modelLabel = 'Format de numérotation';

    protected static ?string $pluralModelLabel = 'Formats de numérotation';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('type_document')->label('Type de document')->required()->unique(ignoreRecord: true),
            TextInput::make('libelle')->label('Libellé')->required()->maxLength(100),
            TextInput::make('format')
                ->label('Pattern configurable')
                ->required()
                ->regex('/\{\{?SEQ:\d{1,2}\}\}?/i')
                ->helperText('Variables : {ANNEE_SCOLAIRE}, {NIVEAU}, {MOIS}, {SEQ:5}. Le format final reste à valider avec Joel et Mme Daina.'),
            Select::make('reinitialisation')
                ->label('Réinitialisation du compteur')
                ->options([
                    'jamais' => 'Jamais',
                    'annee_scolaire' => 'À chaque année scolaire',
                    'mois' => 'À chaque mois',
                ])
                ->default('jamais')
                ->required(),
            TextInput::make('prochain_numero')->label('Prochain numéro')->numeric()->minValue(1)->default(1)->required(),
            Toggle::make('a_valider')
                ->label('Format de démonstration à valider')
                ->helperText('Désactivez cette mention uniquement après validation du format officiel.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('type_document')->label('Type')->searchable()->sortable(),
            TextColumn::make('libelle')->label('Document')->searchable(),
            TextColumn::make('format')->searchable(),
            TextColumn::make('reinitialisation')->label('Réinitialisation')->badge(),
            TextColumn::make('prochain_numero')->label('Prochain numéro')->numeric()->sortable(),
            TextColumn::make('a_valider')->label('Validation')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'À valider' : 'Validé')->color(fn (bool $state): string => $state ? 'warning' : 'success'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => FormatNumerotationResource\Pages\ListFormatsNumerotation::route('/'), 'create' => FormatNumerotationResource\Pages\CreateFormatNumerotation::route('/create'), 'edit' => FormatNumerotationResource\Pages\EditFormatNumerotation::route('/{record}/edit')];
    }
}
