<?php

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Models\SanctionPersonnel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SanctionPersonnelResource extends Resource
{
    protected static ?string $model = SanctionPersonnel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Sanction disciplinaire';

    protected static ?string $pluralModelLabel = 'Discipline du Personnel';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employe_id')
                ->relationship('employe', 'nom')
                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nom} {$record->prenom}")
                ->searchable()
                ->required(),
            Select::make('type')
                ->options([
                    'avertissement' => 'Avertissement',
                    'avertissement_verbal' => 'Avertissement Verbal',
                    'avertissement_ecrit' => 'Avertissement Écrit',
                    'demande_explication' => 'Demande d\'explication',
                    'blame' => 'Blâme',
                    'mise_en_demeure' => 'Mise en Demeure',
                    'suspension' => 'Suspension Temporaire',
                    'licenciement' => 'Licenciement Disciplinaire',
                ])
                ->required(),
            Textarea::make('motif')
                ->required(),
            DatePicker::make('date_sanction')
                ->required(),
            TextInput::make('duree_jours')
                ->numeric()
                ->helperText('Uniquement pour suspension'),
            Select::make('statut')
                ->options([
                    'en_attente_validation' => 'En attente validation',
                    'validee' => 'Validée',
                    'annulee' => 'Annulée',
                ])
                ->default('en_attente_validation')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employe.nom_complet')->label('Employé')->sortable()->searchable(),
                TextColumn::make('type')->sortable(),
                TextColumn::make('date_sanction')->date()->sortable(),
                TextColumn::make('duree_jours')->label('Durée (j)'),
                TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'validee' => 'danger',
                        'en_attente_validation' => 'warning',
                        'annulee' => 'gray',
                        default => 'primary',
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => SanctionPersonnelResource\Pages\ListSanctionPersonnels::route('/'),
            'create' => SanctionPersonnelResource\Pages\CreateSanctionPersonnel::route('/create'),
            'edit' => SanctionPersonnelResource\Pages\EditSanctionPersonnel::route('/{record}/edit'),
        ];
    }
}
