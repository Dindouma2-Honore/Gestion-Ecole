<?php

namespace App\Modules\RH\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Filament\Support\RhActions;
use App\Modules\RH\Models\BulletinPaie;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BulletinPaieResource extends Resource
{
    protected static ?string $model = BulletinPaie::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Bulletin de paie';

    protected static ?string $pluralModelLabel = 'Bulletins de Paie';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employe_id')
                ->relationship('employe', 'nom')
                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nom} {$record->prenom}")
                ->searchable()
                ->required(),
            Select::make('contrat_id')
                ->relationship('contrat', 'id')
                ->getOptionLabelFromRecordUsing(fn ($record) => "Contrat #{$record->id} ({$record->type}) - {$record->salaire_base} FCFA")
                ->required(),
            TextInput::make('mois')
                ->numeric()
                ->minValue(1)
                ->maxValue(12)
                ->required(),
            TextInput::make('annee')
                ->numeric()
                ->required(),
            DatePicker::make('periode_debut')->label('Début de période'),
            DatePicker::make('periode_fin')->label('Fin de période')->afterOrEqual('periode_debut'),
            TextInput::make('salaire_base')
                ->numeric()
                ->prefix('FCFA')
                ->required(),
            TextInput::make('total_primes')
                ->numeric()
                ->prefix('FCFA')
                ->default(0.00),
            TextInput::make('total_retenues')
                ->numeric()
                ->prefix('FCFA')
                ->default(0.00),
            TextInput::make('total_cotisations')
                ->numeric()
                ->prefix('FCFA')
                ->default(0.00),
            TextInput::make('avances_deduites')
                ->numeric()
                ->prefix('FCFA')
                ->default(0.00),
            TextInput::make('net_a_payer')
                ->numeric()
                ->prefix('FCFA')
                ->required(),
            Select::make('statut')
                ->options([
                    'brouillon' => 'Brouillon',
                    'calcule' => 'Calculé',
                    'valide' => 'Validé',
                    'paye' => 'Payé',
                    'annule' => 'Annulé',
                ])
                ->default('calcule')
                ->required(),
            DatePicker::make('date_paiement'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('employe.nom_complet')->label('Employé'),
            TextEntry::make('contrat.type')->label('Contrat'),
            TextEntry::make('periode_debut')->label('Début de période')->date('d/m/Y'),
            TextEntry::make('periode_fin')->label('Fin de période')->date('d/m/Y'),
            TextEntry::make('periode_paiement')
                ->label('Période de paiement')
                ->state(fn ($record): string => $record->periode_debut && $record->periode_fin
                    ? $record->periode_debut->format('d/m/Y').' au '.$record->periode_fin->format('d/m/Y')
                    : sprintf('%02d/%d', $record->mois, $record->annee))
                ->weight('bold'),
            TextEntry::make('salaire_base')->label('Salaire de base')->money('XAF'),
            TextEntry::make('total_primes')->label('Total primes')->money('XAF'),
            TextEntry::make('total_retenues')->label('Retenues')->money('XAF'),
            TextEntry::make('total_cotisations')->label('Cotisations')->money('XAF'),
            TextEntry::make('avances_deduites')->label('Avances déduites')->money('XAF'),
            TextEntry::make('net_a_payer')->label('Net à payer')->money('XAF')->weight('bold'),
            TextEntry::make('statut')->label('Statut')->badge(),
            RepeatableEntry::make('lignes')
                ->label('Détail de la paie')
                ->schema([
                    TextEntry::make('libelle')->label('Rubrique'),
                    TextEntry::make('type')->label('Type')->badge(),
                    TextEntry::make('montant')->label('Montant')->money('XAF'),
                    TextEntry::make('date_reference')->label('Date de référence')->date('d/m/Y')->placeholder('Période du bulletin'),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        AmbassadorsDesign::configureTable($table, null);

        return $table
            ->columns([
                TextColumn::make('employe.nom_complet')
                    ->label('Employé')
                    ->width('24%')
                    ->grow()
                    ->sortable()
                    ->searchable(false)
                    ->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('periode')->label('Période')->state(fn (BulletinPaie $record): string => $record->periode_debut && $record->periode_fin
                    ? $record->periode_debut->format('d/m/Y').' — '.$record->periode_fin->format('d/m/Y')
                    : str_pad((string) $record->mois, 2, '0', STR_PAD_LEFT).'/'.$record->annee)->sortable(['annee', 'mois']),
                TextColumn::make('salaire_base')->money('XAF')->sortable(),
                TextColumn::make('total_primes')->money('XAF')->sortable(),
                TextColumn::make('deductions')->label('Déductions')->state(fn (BulletinPaie $record): float => (float) $record->total_retenues + (float) $record->total_cotisations + (float) $record->avances_deduites)->money('XAF'),
                TextColumn::make('net_a_payer')->money('XAF')->sortable(),
                TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paye' => 'success',
                        'valide' => 'info',
                        'calcule', 'brouillon' => 'warning',
                        'annule' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('date_paiement')->label('Payé le')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_cotisations')->label('Cotisations')->money('XAF')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('avances_deduites')->label('Avances')->money('XAF')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'calcule' => 'Calculé',
                        'valide' => 'Validé',
                        'paye' => 'Payé',
                        'annule' => 'Annulé',
                    ]),
                SelectFilter::make('mois')
                    ->options(array_combine(range(1, 12), [
                        'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
                    ])),
                SelectFilter::make('annee')
                    ->options(fn (): array => BulletinPaie::query()
                        ->distinct()
                        ->pluck('annee', 'annee')
                        ->all()),
            ])
            ->searchable()
            ->searchUsing(function (Builder $query, string $search): void {
                $query->whereHas('employe', function (Builder $employeQuery) use ($search): void {
                    $employeQuery
                        ->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%");
                });
            })
            ->actions([
                ActionGroup::make([
                    RhActions::view()->label('Voir')->modalHeading('Détails du bulletin de paie'),
                    RhActions::edit()->label('Modifier'),
                    Action::make('telecharger_pdf')
                        ->label('Télécharger PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->url(fn (BulletinPaie $record): string => route('rh.bulletins-paie.telecharger', $record))
                        ->openUrlInNewTab(),
                    Action::make('imprimer_pdf')
                        ->label('Imprimer')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->url(fn (BulletinPaie $record): string => route('rh.bulletins-paie.imprimer', $record))
                        ->openUrlInNewTab(),
                    Action::make('valider')
                        ->label('Valider')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (BulletinPaie $record): bool => in_array($record->statut, ['brouillon', 'calcule'], true))
                        ->requiresConfirmation()
                        ->modalHeading('Valider le bulletin de paie')
                        ->modalDescription('Une fois validé, le bulletin ne pourra plus être modifié ni recalculé.')
                        ->action(function (BulletinPaie $record): void {
                            try {
                                app(PaieServiceContract::class)->validerBulletin($record->id, (int) Auth::id());
                                Notification::make()
                                    ->title('Bulletin validé avec succès')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Erreur lors de la validation')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('payer')
                        ->label('Marquer payé')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('primary')
                        ->visible(fn (BulletinPaie $record): bool => $record->statut === 'valide')
                        ->requiresConfirmation()
                        ->modalHeading('Enregistrer le décaissement et marquer payé')
                        ->modalDescription('Cette action va enregistrer le mouvement de décaissement auprès du service Finance et marquer le bulletin comme payé.')
                        ->action(function (BulletinPaie $record): void {
                            try {
                                app(PaieServiceContract::class)->marquerPaye($record->id, now());
                                Notification::make()
                                    ->title('Bulletin payé et décaissement Finance enregistré')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Échec du paiement')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => BulletinPaieResource\Pages\ListBulletinPaies::route('/'),
            'create' => BulletinPaieResource\Pages\CreateBulletinPaie::route('/create'),
            'edit' => BulletinPaieResource\Pages\EditBulletinPaie::route('/{record}/edit'),
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
        if (! static::canAccess()) {
            return false;
        }

        /** @var BulletinPaie $record */
        return in_array($record->statut, ['brouillon', 'calcule'], true);
    }

    public static function canDelete(Model $record): bool
    {
        if (! static::canAccess()) {
            return false;
        }

        /** @var BulletinPaie $record */
        return in_array($record->statut, ['brouillon', 'calcule'], true);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }
}
