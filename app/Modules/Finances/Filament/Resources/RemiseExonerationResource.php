<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Models\EleveFinancier;
use App\Modules\Finances\Models\RemiseExoneration;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RemiseExonerationResource extends Resource
{
    protected static ?string $model = EleveFinancier::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Remise / exonération';

    protected static ?string $pluralModelLabel = 'Remises & exonérations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('eleve_id')
                    ->label('Élève')
                    ->options(function (): array {
                        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();

                        return self::elevesInscrits($anneeId)
                            ->orderBy('nom')->orderBy('prenom')->get()
                            ->mapWithKeys(function (EleveFinancier $eleve) use ($anneeId): array {
                                $classe = self::classeInscription($eleve->id, $anneeId);

                                return [$eleve->id => trim("{$eleve->nom} {$eleve->prenom}")." — {$classe}"];
                            })->all();
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('type')
                    ->label('Nature')
                    ->options([
                        'remise_pourcentage' => 'Remise en pourcentage',
                        'remise_montant' => 'Remise en montant fixe',
                        'exoneration_totale' => 'Exonération totale',
                    ])
                    ->live()
                    ->required(),
                TextInput::make('valeur')
                    ->label(fn (Get $get): string => $get('type') === 'remise_pourcentage' ? 'Pourcentage' : 'Montant')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(fn (Get $get): ?int => $get('type') === 'remise_pourcentage' ? 100 : null)
                    ->suffix(fn (Get $get): string => $get('type') === 'remise_pourcentage' ? '%' : 'FCFA')
                    ->visible(fn (Get $get): bool => $get('type') !== 'exoneration_totale')
                    ->required(fn (Get $get): bool => $get('type') !== 'exoneration_totale'),
                Textarea::make('motif')
                    ->required()
                    ->helperText('Obligatoire — journalisé dans l’audit A.4.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table
            ->columns([
                TextColumn::make('matricule_permanent')
                    ->label('Matricule')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nom')
                    ->label('Élève')
                    ->formatStateUsing(fn (EleveFinancier $record): string => trim("{$record->nom} {$record->prenom}"))
                    ->searchable(['nom', 'prenom'])
                    ->sortable(),
                TextColumn::make('classe_courante')
                    ->label('Classe')
                    ->getStateUsing(function (EleveFinancier $record): string {
                        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();

                        return self::classeInscription($record->id, $anneeId);
                    }),
                TextColumn::make('exoneration_courante')
                    ->label('Scolarité')
                    ->getStateUsing(function (EleveFinancier $record): string {
                        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                        $remise = RemiseExoneration::query()
                            ->where('eleve_id', $record->id)
                            ->where('annee_scolaire_id', $anneeId)
                            ->where('type_frais', 'scolarite')
                            ->latest('id')->first();

                        if ($remise === null) {
                            return 'Aucune exonération';
                        }

                        return match ($remise->type) {
                            'exoneration_totale' => 'Exonération totale',
                            'remise_pourcentage' => 'Remise de '.number_format((float) $remise->valeur, 0, ',', ' ').' %',
                            'remise_montant' => 'Remise de '.number_format((float) $remise->valeur, 0, ',', ' ').' FCFA',
                            default => 'Remise accordée',
                        };
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Aucune exonération' ? 'gray' : 'success'),
                TextColumn::make('nombre_decisions')
                    ->label('Historique')
                    ->getStateUsing(fn (EleveFinancier $record): int => RemiseExoneration::query()
                        ->where('eleve_id', $record->id)
                        ->where('type_frais', 'scolarite')
                        ->count())
                    ->formatStateUsing(fn (int $state): string => $state.' décision'.($state > 1 ? 's' : '')),
            ])
            ->defaultSort('nom')
            ->searchPlaceholder('Rechercher un élève')
            ->recordActions([
                Action::make('accorder_exoneration')
                    ->label('Accorder')
                    ->icon('heroicon-o-receipt-percent')
                    ->modalHeading(fn (EleveFinancier $record): string => 'Remise ou exonération — '.trim("{$record->nom} {$record->prenom}"))
                    ->schema([
                        Select::make('type')
                            ->label('Nature')
                            ->options([
                                'remise_pourcentage' => 'Remise en pourcentage',
                                'remise_montant' => 'Remise en montant fixe',
                                'exoneration_totale' => 'Exonération totale',
                            ])
                            ->live()
                            ->required(),
                        TextInput::make('valeur')
                            ->label(fn (Get $get): string => $get('type') === 'remise_pourcentage' ? 'Pourcentage' : 'Montant')
                            ->numeric()->minValue(0)
                            ->maxValue(fn (Get $get): ?int => $get('type') === 'remise_pourcentage' ? 100 : null)
                            ->suffix(fn (Get $get): string => $get('type') === 'remise_pourcentage' ? '%' : 'FCFA')
                            ->visible(fn (Get $get): bool => $get('type') !== 'exoneration_totale')
                            ->required(fn (Get $get): bool => $get('type') !== 'exoneration_totale'),
                        Textarea::make('motif')->label('Motif')->required(),
                    ])
                    ->action(fn (EleveFinancier $record, array $data) => app(FraisScolaireServiceContract::class)->accorderRemise(
                        $record->id,
                        'scolarite',
                        $data['type'],
                        $data['type'] === 'exoneration_totale' ? 0 : (float) $data['valeur'],
                        $data['motif'],
                    )),
            ]), static::class);
    }

    public static function getEloquentQuery(): Builder
    {
        return self::elevesInscrits(app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());
    }

    private static function elevesInscrits(int $anneeId): Builder
    {
        return EleveFinancier::query()->whereExists(fn ($query) => $query
            ->selectRaw('1')->from('inscriptions')
            ->whereColumn('inscriptions.eleve_id', 'eleves.id')
            ->where('inscriptions.annee_scolaire_id', $anneeId)
            ->whereIn('inscriptions.statut', ['active', 'validee'])
            ->whereNull('inscriptions.deleted_at'));
    }

    private static function classeInscription(int $eleveId, int $anneeId): string
    {
        return (string) (DB::table('inscriptions')->join('classes', 'classes.id', '=', 'inscriptions.classe_id')
            ->where('inscriptions.eleve_id', $eleveId)
            ->where('inscriptions.annee_scolaire_id', $anneeId)
            ->whereIn('inscriptions.statut', ['active', 'validee'])
            ->whereNull('inscriptions.deleted_at')->latest('inscriptions.id')->value('classes.nom') ?? 'Sans classe');
    }

    public static function getPages(): array
    {
        return [
            'index' => RemiseExonerationResource\Pages\ListRemisesExonerations::route('/'),
            'create' => RemiseExonerationResource\Pages\CreateRemiseExoneration::route('/create'),
        ];
    }
}
