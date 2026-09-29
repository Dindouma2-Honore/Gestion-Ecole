<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Modules\Scolarite\Contracts\PaiementServiceContract;
use App\Modules\Scolarite\Exceptions\MontantVersementExcedentaireException;
use App\Modules\Scolarite\Filament\Resources\PaiementResource\Pages;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Paiement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Registre des versements. La création reste pilotée par
 * PaiementServiceContract::enregistrerPaiement() (répartition en cascade,
 * refus du surplus, etc.) — jamais un simple CreateRecord Filament — via
 * le bouton "Créer" (voir creerVersementAction() / ListPaiements) ou via
 * l'action "Encaisser un versement" sur la fiche d'une inscription (voir
 * InscriptionResource\Pages\ViewInscription).
 */
class PaiementResource extends Resource
{
    protected static ?string $model = Paiement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Versements';

    protected static ?string $modelLabel = 'versement';

    protected static ?string $pluralModelLabel = 'versements';

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    /**
     * Action "Créer" du registre : mêmes règles que sur la fiche
     * d'inscription (voir ViewInscription::getHeaderActions()), mais avec
     * un choix préalable de l'inscription concernée.
     */
    public static function creerVersementAction(): Action
    {
        return Action::make('creerVersement')
            ->label('Créer')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->form([
                Select::make('inscription_id')
                    ->label('Élève')
                    ->placeholder('Rechercher ou sélectionner un élève...')
                    ->options(fn () => Inscription::query()
                        ->where('statut', 'en_attente_versement')
                        ->with(['eleve', 'classe'])
                        ->get()
                        ->mapWithKeys(fn (Inscription $i) => [
                            $i->id => trim("{$i->eleve?->prenom} {$i->eleve?->nom}").' — '.($i->classe?->nom ?? '—'),
                        ]))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Inscription::query()
                        ->where('statut', 'en_attente_versement')
                        ->with(['eleve', 'classe'])
                        ->whereHas('eleve', fn ($q) => $q->where('nom', 'like', "%{$search}%")->orWhere('prenom', 'like', "%{$search}%"))
                        ->get()
                        ->mapWithKeys(fn (Inscription $i) => [
                            $i->id => trim("{$i->eleve?->prenom} {$i->eleve?->nom}").' — '.($i->classe?->nom ?? '—'),
                        ]))
                    ->required()
                    ->live(),

                Placeholder::make('montant_a_payer')
                    ->label('Montant à payer')
                    ->content(fn ($get) => filled($get('inscription_id'))
                        ? number_format(
                            app(PaiementServiceContract::class)->getResteAPayer((int) $get('inscription_id')), 0, ',', ' '
                        ).' XAF'
                        : '—')
                    ->visible(fn ($get) => filled($get('inscription_id'))),

                TextInput::make('montant')
                    ->label('Montant versé')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->suffix('XAF')
                    ->visible(fn ($get) => filled($get('inscription_id'))),

                Select::make('mode')
                    ->label('Mode de paiement')
                    ->options([
                        'especes' => 'Espèces',
                        'bancaire' => 'Virement / dépôt bancaire',
                        'mobile_money' => 'Mobile Money',
                    ])
                    ->required()
                    ->live(),

                TextInput::make('reference_mobile_money')
                    ->label('Référence Mobile Money')
                    ->visible(fn ($get) => $get('mode') === 'mobile_money')
                    ->required(fn ($get) => $get('mode') === 'mobile_money'),

                Section::make('Aperçu du reçu')
                    ->description('Générés automatiquement à l’enregistrement du versement.')
                    ->schema([
                        Grid::make(3)->schema([
                            Placeholder::make('numero_recu_apercu')
                                ->label('N° reçu')
                                ->content('Généré à la validation'),

                            Placeholder::make('statut_apercu')
                                ->label('Statut')
                                ->content('Valide'),

                            Placeholder::make('encaisse_le_apercu')
                                ->label('Encaissé le')
                                ->content(fn () => Carbon::now()->format('d/m/Y H:i')),
                        ]),
                    ])
                    ->collapsible(false),
            ])
            ->action(function (array $data): void {
                try {
                    app(PaiementServiceContract::class)->enregistrerPaiement(
                        inscriptionId: (int) $data['inscription_id'],
                        montant: (float) $data['montant'],
                        mode: $data['mode'],
                        referenceMobileMoney: $data['reference_mobile_money'] ?? null,
                    );
                } catch (MontantVersementExcedentaireException $e) {
                    Notification::make()
                        ->title('Montant refusé')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Versement enregistré')
                    ->success()
                    ->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Paiement::query()->with(['inscription.eleve']))
            ->columns([
                Tables\Columns\TextColumn::make('numero_recu')->label('N° reçu')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('inscription.eleve.nom')
                    ->label('Élève')
                    ->formatStateUsing(fn (Paiement $record): string => trim("{$record->inscription?->eleve?->prenom} {$record->inscription?->eleve?->nom}"))
                    ->searchable(['inscription.eleve.nom', 'inscription.eleve.prenom']),

                Tables\Columns\TextColumn::make('montant')->label('Montant')->money('XAF')->sortable(),

                Tables\Columns\TextColumn::make('mode')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'especes' => 'Espèces',
                        'bancaire' => 'Bancaire',
                        'mobile_money' => 'Mobile Money',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'valide' ? 'Valide' : 'Annulé')
                    ->color(fn (string $state): string => $state === 'valide' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('created_at')->label('Encaissé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')->options([
                    'valide' => 'Valide',
                    'annule' => 'Annulé',
                ]),
                Tables\Filters\SelectFilter::make('mode')->options([
                    'especes' => 'Espèces',
                    'bancaire' => 'Bancaire',
                    'mobile_money' => 'Mobile Money',
                ]),
            ])
            ->actions([
                Action::make('annuler')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Paiement $record) => $record->statut === 'valide')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('motif')->label('Motif d’annulation')->required(),
                    ])
                    ->action(fn (Paiement $record, array $data) => app(PaiementServiceContract::class)->annulerPaiement($record->id, $data['motif'])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaiements::route('/'),
        ];
    }
}
