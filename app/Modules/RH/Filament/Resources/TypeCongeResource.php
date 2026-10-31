<?php

namespace App\Modules\RH\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Support\RhActions;
use App\Modules\RH\Models\TypeConge;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TypeCongeResource extends Resource
{
    protected static ?string $model = TypeConge::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Type de congé';

    protected static ?string $pluralModelLabel = 'Types de congés';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom')->required()->unique(ignoreRecord: true),
            Textarea::make('description')->label('Description'),
            Toggle::make('remunere')->label('Rémunéré')->default(true),
            Select::make('impact_paie')
                ->label('Impact sur la paie')
                ->options([
                    'aucun' => 'Aucun',
                    'deduction' => 'Déduction',
                ])
                ->default('aucun')
                ->live()
                ->required(),
            Toggle::make('decompte_solde')->label('Décompte du solde de congés')->default(false),
            Toggle::make('justificatif_obligatoire')->label('Justificatif obligatoire')->default(false),
            Toggle::make('validation_requise')->label('Validation requise')->default(true),
            Select::make('methode_deduction')
                ->label('Méthode de déduction')
                ->options(['jours_non_remuneres' => 'Selon jours non rémunérés'])
                ->visible(fn ($get): bool => $get('impact_paie') === 'deduction')
                ->required(fn ($get): bool => $get('impact_paie') === 'deduction'),
            Toggle::make('actif')->label('Actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        AmbassadorsDesign::configureTable($table, null);

        return $table->columns([
            TextColumn::make('nom')->label('Type')->searchable()->sortable(),
            IconColumn::make('remunere')->label('Rémunéré')->boolean(),
            TextColumn::make('impact_paie')->label('Impact paie')->formatStateUsing(fn (string $state): string => $state === 'deduction' ? 'Déduction' : 'Aucun'),
            IconColumn::make('actif')->label('Actif')->boolean(),
            TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y')->sortable(),
        ])->defaultSort('created_at', 'desc')
            ->recordActions([RhActions::edit()->label('Modifier')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => TypeCongeResource\Pages\ListTypeConges::route('/'),
            'create' => TypeCongeResource\Pages\CreateTypeConge::route('/create'),
            'edit' => TypeCongeResource\Pages\EditTypeConge::route('/{record}/edit'),
        ];
    }
}
