<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Models\User;
use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource\Pages;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AffectationPedagogiqueResource extends Resource
{
    protected static ?string $model = AffectationPedagogique::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $modelLabel = 'Affectation pédagogique';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('annee_scolaire_id')->label('Année')->options(fn (): array => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())->pluck('libelle', 'id')->all())->required()->live(),
            Select::make('classe_id')->label('Classe')->options(fn (callable $get): array => $get('annee_scolaire_id') ? collect(app(ClasseServiceInterface::class)->getToutesLesClasses((int) $get('annee_scolaire_id')))->pluck('nom', 'id')->all() : [])->required(),
            Select::make('enseignant_id')->label('Enseignant')->options(fn () => User::role('Enseignant')->pluck('name', 'id'))->searchable()->required(),
            Select::make('matiere_id')->relationship('matiere', 'nom')->searchable()->preload()->required(),
            Select::make('offre_pedagogique_id')->label('Offre pédagogique')->relationship('offre', 'id')->getOptionLabelFromRecordUsing(fn ($record): string => $record->matiere->nom.' — coef. '.$record->coefficient_matiere)->searchable()->preload(),
            TextInput::make('coefficient')->label('Coefficient pour cette classe')->numeric()->helperText('Laisser vide pour utiliser le coefficient par défaut.'),
            Toggle::make('actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('matiere.nom')->label('Matière'), TextColumn::make('classe_id')->label('Classe'), TextColumn::make('enseignant_id')->label('Enseignant'), TextColumn::make('coefficient')->label('Coefficient')->placeholder('Par défaut'), TextColumn::make('annee_scolaire_id')->label('Année'), IconColumn::make('actif')->boolean()])->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAffectationsPedagogiques::route('/'), 'create' => Pages\CreateAffectationPedagogique::route('/create'), 'edit' => Pages\EditAffectationPedagogique::route('/{record}/edit')];
    }
}
