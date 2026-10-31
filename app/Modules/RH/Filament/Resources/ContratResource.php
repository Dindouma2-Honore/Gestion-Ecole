<?php

namespace App\Modules\RH\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Contracts\ContratServiceInterface;
use App\Modules\RH\Filament\Support\RhActions;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PosteAdministratif;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ContratResource extends Resource
{
    protected static ?string $model = Contrat::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Contrat de travail';

    protected static ?string $pluralModelLabel = 'Contrats de travail';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employe_id')
                ->label('Personnel')
                ->options(function (): array {
                    return Employe::query()
                        ->where('statut', 'actif')
                        ->orderBy('nom')
                        ->orderBy('prenom')
                        ->get()
                        ->mapWithKeys(function (Employe $employe): array {
                            $label = $employe->nom_complet;
                            if ($employe->matricule) {
                                $label .= " ({$employe->matricule})";
                            }
                            if ($employe->poste) {
                                $label .= " — {$employe->poste}";
                            }

                            return [$employe->id => $label];
                        })
                        ->all();
                })
                ->searchable()
                ->preload()
                ->required()
                ->disabledOn('edit')
                ->dehydrated(),
            Select::make('type')
                ->options([
                    'CDI' => 'CDI (Durée Indéterminée)',
                    'CDD' => 'CDD (Durée Déterminée)',
                    'Stage' => 'Stage',
                    'Prestataire' => 'Prestataire / Vacataire',
                ])
                ->required(),
            Select::make('categorie_paie')
                ->label('Formule selon les responsabilités')
                ->options([
                    'fixe' => 'Salaire de base + primes (maternelle, primaire, secondaire, auxiliaire, autres staff)',
                    'horaire' => 'Taux horaire × heures (autres responsabilités)',
                    'mixte' => 'Cumul des deux formules',
                ])
                ->default('fixe')->live()->required(),
            DatePicker::make('date_debut')
                ->label('Début du contrat')
                ->default(fn (): string => today()->toDateString())
                ->required(),
            DatePicker::make('date_fin')
                ->label('Fin du contrat')
                ->afterOrEqual('date_debut')
                ->nullable()
                ->helperText('Obligatoire pour un CDD ou Stage. Laisser vide pour un CDI.'),
            DatePicker::make('periode_essai_fin')
                ->label('Fin de la période d’essai')
                ->afterOrEqual('date_debut')
                ->nullable()
                ->helperText('Facultatif — date de fin de la période d’essai.'),
            TextInput::make('salaire_base')
                ->label('Salaire de base personnel')
                ->numeric()
                ->minValue(0)
                ->prefix('FCFA')
                ->visible(fn (Get $get): bool => in_array($get('categorie_paie'), ['fixe', 'mixte'], true))
                ->required(fn (Get $get): bool => in_array($get('categorie_paie'), ['fixe', 'mixte'], true)),
            TextInput::make('taux_horaire')
                ->label('Taux horaire')
                ->numeric()->minValue(0)->prefix('FCFA')
                ->visible(fn (Get $get): bool => in_array($get('categorie_paie'), ['horaire', 'mixte'], true))
                ->required(fn (Get $get): bool => in_array($get('categorie_paie'), ['horaire', 'mixte'], true)),
            Select::make('fonction_id')
                ->label('Fonction complémentaire')
                ->options(fn (): array => PosteAdministratif::query()
                    ->where('actif', true)
                    ->orderBy('nom')
                    ->pluck('nom', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->visibleOn('edit')
                ->dehydrated(false)
                ->helperText('Une fonction existante peut être ajoutée au renouvellement sans modifier le salaire du rôle.'),
            Select::make('fonction_mode_remuneration')
                ->label('Méthode de rémunération de la fonction')
                ->options([
                    'salaire_base' => 'Salaire de base',
                    'tarif_horaire' => 'Tarif par heures',
                ])
                ->live()
                ->visible(fn (Get $get): bool => filled($get('fonction_id')))
                ->dehydrated(false),
            TextInput::make('fonction_montant')
                ->label(fn (Get $get): string => $get('fonction_mode_remuneration') === 'tarif_horaire' ? 'Tarif par heures' : 'Salaire de base')
                ->numeric()
                ->minValue(0.01)
                ->prefix('FCFA')
                ->visible(fn (Get $get): bool => filled($get('fonction_id')) && filled($get('fonction_mode_remuneration')))
                ->required(fn (Get $get): bool => filled($get('fonction_id')) && filled($get('fonction_mode_remuneration')))
                ->dehydrated(false),
            Select::make('statut')
                ->options([
                    'actif' => 'Actif',
                    'suspendu' => 'Suspendu',
                    'expire' => 'Expiré',
                    'resilie' => 'Résilié',
                ])
                ->default('actif')
                ->visibleOn('edit')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        AmbassadorsDesign::configureTable($table, null);

        return $table
            ->columns([
                TextColumn::make('employe.nom_complet')
                    ->label('Employé')
                    ->width('25%')
                    ->grow()
                    ->sortable()
                    ->searchable(false)
                    ->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('type')->sortable(),
                TextColumn::make('categorie_paie')->label('Paie')->badge(),
                TextColumn::make('periode')->label('Période')->state(fn (Contrat $record): string => $record->date_debut->format('d/m/Y').' — '.($record->date_fin?->format('d/m/Y') ?? 'Indéterminée')),
                TextColumn::make('remuneration')->label('Rémunération')->state(fn (Contrat $record): string => match ($record->categorie_paie) {
                    'horaire' => number_format((float) $record->taux_horaire, 0, ',', ' ').' FCFA/h',
                    'mixte' => number_format((float) $record->salaire_base, 0, ',', ' ').' + horaire',
                    default => number_format((float) $record->salaire_base, 0, ',', ' ').' FCFA',
                }),
                TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'actif' => 'success',
                        'suspendu' => 'warning',
                        'expire', 'resilie' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->options([
                        'actif' => 'Actif',
                        'suspendu' => 'Suspendu',
                        'expire' => 'Expiré',
                        'resilie' => 'Résilié',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'CDI' => 'CDI',
                        'CDD' => 'CDD',
                        'Stage' => 'Stage',
                        'Prestataire' => 'Prestataire',
                    ]),
            ])
            ->searchable()
            ->searchUsing(function (Builder $query, string $search): void {
                $query->whereHas('employe', function (Builder $employeQuery) use ($search): void {
                    $employeQuery
                        ->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%");
                });
            })
            ->actions([ActionGroup::make([
                RhActions::view()->label('Voir')->modalHeading('Détails du contrat'),
                RhActions::edit()->label('Modifier'),
                Action::make('suspendre')
                    ->label('Suspendre')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn (Contrat $record): bool => $record->statut === 'actif')
                    ->requiresConfirmation()
                    ->modalHeading('Suspendre le contrat')
                    ->modalDescription('Êtes-vous sûr de vouloir suspendre ce contrat ?')
                    ->action(function (Contrat $record): void {
                        $record->update(['statut' => 'suspendu']);
                        Notification::make()
                            ->title('Contrat suspendu')
                            ->warning()
                            ->send();
                    }),
                Action::make('reactiver')
                    ->label('Réactiver')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (Contrat $record): bool => $record->statut === 'suspendu')
                    ->requiresConfirmation()
                    ->modalHeading('Réactiver le contrat')
                    ->modalDescription('Êtes-vous sûr de vouloir réactiver ce contrat ?')
                    ->action(function (Contrat $record): void {
                        $record->update(['statut' => 'actif']);
                        Notification::make()
                            ->title('Contrat réactivé')
                            ->success()
                            ->send();
                    }),
                Action::make('resilier')
                    ->label('Résilier')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Contrat $record): bool => in_array($record->statut, ['actif', 'suspendu'], true))
                    ->form([
                        DatePicker::make('date_effet')
                            ->label('Date d’effet de la résiliation')
                            ->default(fn (): string => today()->toDateString())
                            ->required(),
                        TextInput::make('motif')
                            ->label('Motif de la résiliation')
                            ->required(),
                    ])
                    ->action(function (Contrat $record, array $data): void {
                        try {
                            app(ContratServiceInterface::class)->resilier(
                                $record->id,
                                Carbon::parse($data['date_effet']),
                                $data['motif']
                            );
                            Notification::make()
                                ->title('Contrat résilié avec succès')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Erreur lors de la résiliation')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ContratResource\Pages\ListContrats::route('/'),
            'create' => ContratResource\Pages\CreateContrat::route('/create'),
            'edit' => ContratResource\Pages\EditContrat::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole(['Fondateur', 'Comptable']) ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }
}
