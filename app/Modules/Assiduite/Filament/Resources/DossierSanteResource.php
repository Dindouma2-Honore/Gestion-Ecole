<?php

namespace App\Modules\Assiduite\Filament\Resources;

use App\Modules\Assiduite\Filament\Resources\DossierSanteResource\Pages;
use App\Modules\Assiduite\Models\DossierSante;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DossierSanteResource extends Resource
{
    protected static ?string $model = DossierSante::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|\UnitEnum|null $navigationGroup = 'Assiduité';

    protected static ?string $modelLabel = 'Dossier Santé';

    protected static ?string $pluralModelLabel = 'Dossiers Santé Élèves';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('eleve_id')
                ->relationship('eleve', 'nom')
                ->required()
                ->searchable(),
            Select::make('groupe_sanguin')
                ->options([
                    'A+' => 'A+',
                    'A-' => 'A-',
                    'B+' => 'B+',
                    'B-' => 'B-',
                    'AB+' => 'AB+',
                    'AB-' => 'AB-',
                    'O+' => 'O+',
                    'O-' => 'O-',
                ]),
            Textarea::make('allergies'),
            Textarea::make('maladies_chroniques'),
            Textarea::make('medicaments_autorises'),
            TextInput::make('contact_urgence_nom')
                ->required()
                ->maxLength(255),
            TextInput::make('contact_urgence_telephone')
                ->tel()
                ->required()
                ->maxLength(20),
            TextInput::make('medecin_traitant')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('eleve.nom')->label('Élève')->sortable()->searchable(),
                TextColumn::make('groupe_sanguin')->sortable(),
                TextColumn::make('allergies')->limit(30),
                TextColumn::make('contact_urgence_nom')->label('Contact Urgence'),
                TextColumn::make('contact_urgence_telephone')->label('Téléphone'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDossiersSante::route('/'),
            'create' => Pages\CreateDossierSante::route('/create'),
            'edit' => Pages\EditDossierSante::route('/{record}/edit'),
        ];
    }
}
