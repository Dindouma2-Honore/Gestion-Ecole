<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource\Pages;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OffrePedagogiqueResource extends Resource
{
    protected static ?string $model = OffrePedagogique::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $modelLabel = 'Offre pédagogique';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('matiere_id')->relationship('matiere', 'nom')->required()->searchable()->preload(),
            Select::make('niveau_id')->label('Niveau')->options(fn (): array => collect(app(ParametrageServiceContract::class)->getTousLesNiveaux())->pluck('nom', 'id')->all())->required(),
            Select::make('annee_scolaire_id')->label('Année')->options(fn (): array => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())->pluck('libelle', 'id')->all())->required(),
            Select::make('programme_id')->relationship('programme', 'titre')->searchable()->preload(),
            TextInput::make('coefficient_matiere')->label('Coefficient matière')->numeric()->minValue(.01)->default(1)->required(),
            TextInput::make('volume_horaire')->numeric()->minValue(0),
            TextInput::make('heures_hebdomadaires')->label('Heures hebdomadaires')->numeric()->minValue(0),
            Toggle::make('actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('matiere.nom')->searchable(), TextColumn::make('niveau_id')->label('Niveau'), TextColumn::make('coefficient_matiere')->label('Coefficient'), TextColumn::make('heures_hebdomadaires')->label('H/semaine'), IconColumn::make('actif')->boolean()])->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOffresPedagogiques::route('/'), 'create' => Pages\CreateOffrePedagogique::route('/create'), 'edit' => Pages\EditOffrePedagogique::route('/{record}/edit')];
    }
}
