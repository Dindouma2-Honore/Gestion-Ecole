<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Models\User;
use App\Modules\Pedagogie\Filament\Resources\DecisionPedagogiqueResource\Pages;
use App\Modules\Pedagogie\Models\DecisionPedagogique;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class DecisionPedagogiqueResource extends Resource
{
    protected static ?string $model = DecisionPedagogique::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Décisions & Conseils';

    protected static ?string $modelLabel = 'décision pédagogique';

    protected static ?string $pluralModelLabel = 'décisions du conseil';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('annee_scolaire_id')
                    ->label('Année scolaire')
                    ->options(fn () => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())
                        ->mapWithKeys(fn ($a) => [data_get($a, 'id') => data_get($a, 'libelle', data_get($a, 'nom', '#'.data_get($a, 'id')))]))
                    ->live()
                    ->default(fn () => app(AnneeScolaireServiceContract::class)->getAnneeCouranteId())
                    ->searchable()
                    ->required(),

                Select::make('classe_id')
                    ->label('Classe')
                    ->options(function (Get $get) {
                        $anneeScolaireId = (int) ($get('annee_scolaire_id')
                            ?: app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());

                        return collect(app(ClasseServiceInterface::class)->getToutesLesClasses($anneeScolaireId))
                            ->pluck('nom', 'id');
                    })
                    ->live()
                    ->searchable()
                    ->required(),

                Select::make('eleve_id')
                    ->label('Élève (ID)')
                    ->helperText('Identifiant unique de l’élève')
                    ->required(),

                Select::make('decision')
                    ->label('Décision du conseil')
                    ->options([
                        'admis' => 'Admis en classe supérieure',
                        'admis_sous_condition' => 'Admis sous condition / rattrapage',
                        'redouble' => 'Redoublement',
                        'reoriente' => 'Réorientation',
                        'exclu' => 'Exclusion pédagogique / départ',
                    ])
                    ->required(),

                Textarea::make('motif')
                    ->label('Motif & observations du conseil')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('eleve_id')
                    ->label('Élève')
                    ->formatStateUsing(function ($state) {
                        $eleve = rescue(fn () => app(EleveServiceInterface::class)->getEleve((int) $state), null);

                        return $eleve ? trim(($eleve['nom'] ?? '').' '.($eleve['prenom'] ?? '')) : "Élève #{$state}";
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('classe_id')
                    ->label('Classe')
                    ->formatStateUsing(fn ($state) => rescue(fn () => app(ClasseServiceInterface::class)->getNomClasse((int) $state), "#{$state}")),

                Tables\Columns\TextColumn::make('decision')
                    ->label('Décision')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'admis' => 'success',
                        'admis_sous_condition' => 'warning',
                        'redouble' => 'danger',
                        'exclu' => 'danger',
                        'reoriente' => 'gray',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'admis' => 'Admis',
                        'admis_sous_condition' => 'Admis sous condition',
                        'redouble' => 'Redouble',
                        'exclu' => 'Exclu',
                        'reoriente' => 'Réorienté',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('motif')
                    ->label('Observations')
                    ->limit(50),

                Tables\Columns\TextColumn::make('valide_le')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                SelectFilter::make('decision')
                    ->options([
                        'admis' => 'Admis',
                        'admis_sous_condition' => 'Admis sous condition',
                        'redouble' => 'Redouble',
                        'reoriente' => 'Réorienté',
                        'exclu' => 'Exclu',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDecisionsPedagogiques::route('/'),
        ];
    }
}
