<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Models\User;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Filament\Resources\PaiementResource\Pages;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\SessionCaisse;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Contracts\InscriptionFinanceReadContract;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Throwable;

class PaiementResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('interface.school_payments');
    }

    protected static ?string $model = Paiement::class;

    protected static ?string $slug = 'finances/paiements';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Paiement scolaire';

    protected static ?string $pluralModelLabel = 'Paiements scolaires';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Placeholder::make('caisse_status_alert')
                ->hiddenLabel()
                ->content(function (): ?HtmlString {
                    try {
                        $sessionDuJour = SessionCaisse::whereDate('date_session', today())->first();
                        if ($sessionDuJour && $sessionDuJour->statut === 'cloturee') {
                            return new HtmlString('<div class="rounded-lg bg-danger-50 dark:bg-danger-950/30 p-3 border border-danger-200 dark:border-danger-800 text-danger-800 dark:text-danger-200 text-sm font-medium"><strong>Attention :</strong> La session de caisse pour aujourd\'hui est clôturée. L\'enregistrement de nouveaux paiements est suspendu.</div>');
                        }
                    } catch (Throwable) {
                    }

                    return null;
                })
                ->visible(function (): bool {
                    try {
                        $sessionDuJour = SessionCaisse::whereDate('date_session', today())->first();

                        return $sessionDuJour && $sessionDuJour->statut === 'cloturee';
                    } catch (Throwable) {
                        return false;
                    }
                })
                ->columnSpanFull(),
            Select::make('inscription_id')
                ->label('Inscription')
                ->options(function (): array {
                    try {
                        $anneeCouranteId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();

                        return app(InscriptionFinanceReadContract::class)->getInscriptionsOptions($anneeCouranteId);
                    } catch (Throwable) {
                        return [];
                    }
                })
                ->searchable()
                ->preload()
                ->live()
                ->required(),
            Placeholder::make('reste_a_payer')
                ->label('Reste à payer')
                ->content(function ($get): string {
                    $inscriptionId = (int) $get('inscription_id');
                    if ($inscriptionId <= 0) {
                        return 'Sélectionnez une inscription.';
                    }

                    try {
                        $reste = app(\App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract::class)->getResteAPayer($inscriptionId);

                        return number_format($reste, 0, ',', ' ').' FCFA';
                    } catch (Throwable) {
                        return 'Montant indisponible pour cette inscription.';
                    }
                }),
            TextInput::make('montant')->numeric()->minValue(0.01)->suffix('FCFA')->required(),
            Select::make('mode')
                ->options(['especes' => 'Espèces', 'bancaire' => 'Virement / dépôt bancaire', 'mobile_money' => 'Mobile Money'])
                ->live()
                ->required(),
            TextInput::make('reference_mobile_money')
                ->label('Référence Mobile Money')
                ->visible(fn ($get): bool => $get('mode') === 'mobile_money')
                ->required(fn ($get): bool => $get('mode') === 'mobile_money')
                ->maxLength(100),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('numero_recu')->label('N° reçu')->weight('bold'),
            TextEntry::make('eleve_id')->label('Élève')->formatStateUsing(function ($state): string {
                try {
                    $eleve = app(EleveServiceInterface::class)->getEleve((int) $state);

                    return trim($eleve['prenom'].' '.$eleve['nom']);
                } catch (Throwable) {
                    return 'Élève #'.$state;
                }
            }),
            TextEntry::make('montant')->label('Montant')->money('XAF')->weight('bold'),
            TextEntry::make('mode')->label('Mode de paiement')->badge(),
            TextEntry::make('reference_mobile_money')->label('Référence Mobile Money')->placeholder('—'),
            TextEntry::make('statut')->label('Statut')->badge()->color(fn (string $state): string => match ($state) {
                'valide' => 'success',
                'annule' => 'danger',
                'rembourse' => 'warning',
                default => 'gray',
            }),
            TextEntry::make('encaisseur.name')->label('Encaissé par')->placeholder('Système'),
            TextEntry::make('created_at')->label('Encaissé le')->dateTime('d/m/Y H:i'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table
            ->columns([
                TextColumn::make('numero_recu')->label('Nº reçu')->searchable()->sortable(),
                TextColumn::make('eleve_id')->label('Élève')->formatStateUsing(function ($state): string {
                    try {
                        $eleve = app(EleveServiceInterface::class)->getEleve((int) $state);

                        return trim($eleve['prenom'].' '.$eleve['nom']);
                    } catch (Throwable) {
                        return 'Élève introuvable';
                    }
                }),
                TextColumn::make('montant')->money('XAF')->sortable(),
                TextColumn::make('mode')->badge(),
                TextColumn::make('statut')->badge()->color(fn (string $state): string => match ($state) {
                    'valide' => 'success',
                    'annule' => 'danger',
                    'rembourse' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('encaisseur.name')->label('Encaissé par'),
                TextColumn::make('created_at')->label('Encaissé le')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')->options(['valide' => 'Validé', 'annule' => 'Annulé', 'rembourse' => 'Remboursé']),
                SelectFilter::make('mode')->options(['especes' => 'Espèces', 'bancaire' => 'Bancaire', 'mobile_money' => 'Mobile Money']),
            ])
            ->recordActions([
                Action::make('voir_recu')
                    ->label('Voir le reçu PDF')
                    ->icon('heroicon-o-receipt-percent')
                    ->color('success')
                    ->url(fn (Paiement $record): ?string => app(DocumentServiceContract::class)
                        ->getUrlTelechargement($record->document_recu_id))
                    ->openUrlInNewTab()
                    ->visible(fn (Paiement $record): bool => $record->document_recu_id !== null),
                Action::make('annuler')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([Textarea::make('motif')->required()])
                    ->visible(function (Paiement $record): bool {
                        /** @var User|null $user */
                        $user = Auth::user();

                        return $record->statut === 'valide'
                            && ($user?->hasAnyRole(['Fondateur', 'Comptable']) ?? false);
                    })
                    ->action(fn (Paiement $record, array $data) => app(PaiementServiceContract::class)
                        ->annulerPaiement($record->id, $data['motif'])),
            ]), static::class);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaiements::route('/'),
            'create' => Pages\CreatePaiement::route('/create'),
        ];
    }
}
