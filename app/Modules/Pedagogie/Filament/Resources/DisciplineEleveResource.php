<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Filament\Resources\DisciplineEleveResource\Pages;
use App\Modules\Pedagogie\Models\DisciplineEleve;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DisciplineEleveResource extends Resource
{
    protected static ?string $model = DisciplineEleve::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Discipline des élèves';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('annee_scolaire_id')->label('Année scolaire')->options(fn () => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())->pluck('libelle', 'id'))->required()->live(),
            Select::make('classe_id')->label('Classe')->options(fn (callable $get) => $get('annee_scolaire_id') ? collect(app(ClasseServiceInterface::class)->getToutesLesClasses((int) $get('annee_scolaire_id')))->pluck('nom', 'id') : [])->live(),
            Select::make('eleve_id')->label('Élève')->options(fn (callable $get) => $get('classe_id') ? collect(app(EleveServiceInterface::class)->getElevesParClasse((int) $get('classe_id')))->mapWithKeys(fn ($e) => [$e['id'] => trim($e['nom'].' '.$e['prenom'])]) : [])->searchable()->required(),
            DatePicker::make('date_incident')->label("Date de l'incident")->default(now())->required(),
            TextInput::make('type_incident')->label("Type d'incident")->required()->maxLength(100),
            Select::make('gravite')->label('Gravité')->options(['mineure' => 'Mineure', 'moyenne' => 'Moyenne', 'grave' => 'Grave', 'critique' => 'Critique'])->required(),
            Textarea::make('description')->required()->columnSpanFull(),
            Textarea::make('mesure_prise')->label('Mesure prise')->columnSpanFull(),
            Toggle::make('confidentiel')->label('Dossier confidentiel')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('date_incident')->label('Date')->date('d/m/Y')->sortable(),
            Tables\Columns\TextColumn::make('eleve_id')->label('Élève')->formatStateUsing(fn ($state) => (($e = app(EleveServiceInterface::class)->getEleve((int) $state)) ? trim(($e['nom'] ?? '').' '.($e['prenom'] ?? '')) : "#{$state}"))->searchable(),
            Tables\Columns\TextColumn::make('type_incident')->label('Incident')->searchable(),
            Tables\Columns\TextColumn::make('gravite')->badge(),
            Tables\Columns\IconColumn::make('confidentiel')->label('Confidentiel')->boolean(),
        ])->actions([EditAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return auth()->user()?->hasAnyRole(['Fondateur', 'Directeur', 'SurveillantGeneral']) ? $query : $query->where('confidentiel', false);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDisciplineEleves::route('/'), 'create' => Pages\CreateDisciplineEleve::route('/create'), 'edit' => Pages\EditDisciplineEleve::route('/{record}/edit')];
    }
}
