<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Models\User;
use App\Modules\Logistique\Contracts\InfrastructureServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource\Pages;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class EmploiDuTempsResource extends Resource
{
    protected static ?string $model = EmploiDuTemps::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Emplois du temps';

    protected static ?string $modelLabel = 'cours planifié';

    protected static ?string $pluralModelLabel = 'emplois du temps';

    /**
     * La détection de conflit (enseignant/salle/classe déjà occupés sur le
     * créneau) vit dans EmploiDuTempsServiceInterface — jamais recalculée ici.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('matiere_id')
                    ->label('Matière')
                    ->options(fn () => Matiere::where('actif', true)->pluck('nom', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('annee_scolaire_id')
                    ->label('Année scolaire')
                    ->options(fn () => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())
                        ->mapWithKeys(fn ($a) => [data_get($a, 'id') => data_get($a, 'libelle', data_get($a, 'nom', '#' . data_get($a, 'id')))]))
                    ->live()
                    ->default(fn () => app(AnneeScolaireServiceContract::class)->getAnneeCouranteId())
                    ->searchable()
                    ->required(),

                Select::make('classe_id')
                    ->label('Classe')
                    ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                        $anneeScolaireId = (int) ($get('annee_scolaire_id')
                            ?: app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());

                        return collect(app(ClasseServiceInterface::class)->getToutesLesClasses($anneeScolaireId))
                            ->pluck('nom', 'id');
                    })
                    ->searchable()
                    ->required(),

                Select::make('enseignant_id')
                    ->label('Enseignant')
                    ->options(fn () => User::role('Enseignant')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('salle_id')
                    ->label('Salle')
                    ->options(fn () => collect(app(InfrastructureServiceInterface::class)->getSallesDisponibles())
                        ->pluck('nom', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('creneau_id')
                    ->label('Créneau')
                    ->relationship('creneau', 'id')
                    ->getOptionLabelFromRecordUsing(
                        fn ($record) => "Jour {$record->jour_semaine} — {$record->heure_debut} à {$record->heure_fin}"
                    )
                    ->required(),

                Toggle::make('actif')
                    ->label('Actif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('matiere.nom')->label('Matière')->searchable(),
                Tables\Columns\TextColumn::make('classe_id')->label('Classe (ID)'),
                Tables\Columns\TextColumn::make('enseignant_id')
                    ->label('Enseignant')
                    ->formatStateUsing(fn ($state) => User::find($state)?->name ?? "#{$state}"),
                Tables\Columns\TextColumn::make('creneau.jour_semaine')->label('Jour'),
                Tables\Columns\TextColumn::make('creneau.heure_debut')->label('Début'),
                Tables\Columns\TextColumn::make('creneau.heure_fin')->label('Fin'),
                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmploisDuTemps::route('/'),
            'create' => Pages\CreateEmploiDuTemps::route('/create'),
            'edit' => Pages\EditEmploiDuTemps::route('/{record}/edit'),
        ];
    }
}
