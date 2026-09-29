<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages;

use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewInscription extends ViewRecord
{
    protected static string $resource = InscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('encaisserVersement')
                ->label('Encaisser un versement')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record->statut === 'en_attente_versement')
                ->form([
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
                ])
                ->action(function (array $data): void {
                    $facture = $this->factureProvisoire();
                    abort_unless($facture, 422, 'La facture provisoire est introuvable.');

                    app(FacturePreinscriptionServiceContract::class)->confirmerVersement(
                        $facture->id,
                        $data['mode'],
                        $data['reference_mobile_money'] ?? null,
                    );

                    Notification::make()
                        ->title('Versement enregistré')
                        ->success()
                        ->send();

                    $this->record->refresh();
                }),

            Action::make('annulerInscription')
                ->label('Annuler l’inscription')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->statut !== 'annulee')
                ->form([
                    Textarea::make('motif')->label('Motif d’annulation')->required(),
                ])
                ->action(function (array $data): void {
                    app(InscriptionServiceInterface::class)->annulerInscription($this->record->id, $data['motif']);
                    $this->record->refresh();
                }),

            Action::make('imprimerFacture')
                ->label('Voir la facture provisoire')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn () => $this->derniereFacture() !== null)
                ->url(fn () => $this->factureProvisoire()
                    ? app(DocumentServiceContract::class)->getUrlTelechargement($this->factureProvisoire()?->document_provisoire_id)
                    : route('impressions.facture', ['facture' => $this->derniereFacture()?->id]))
                ->openUrlInNewTab(),

            Action::make('imprimerRecu')
                ->label('Voir le reçu')
                ->icon('heroicon-o-receipt-percent')
                ->color('success')
                ->visible(fn () => app(InscriptionFacturationPort::class)->getVersements((int) $this->record->id) !== [])
                ->action(function () {
                    $recu = app(InscriptionFacturationPort::class)->getRecuDernierVersement((int) $this->record->id);
                    abort_unless($recu, 404, 'Le reçu est introuvable.');

                    return response()->streamDownload(
                        static fn () => print ($recu['contenu']),
                        $recu['nom'],
                        ['Content-Type' => 'application/pdf'],
                    );
                }),
        ];
    }

    private function derniereFacture()
    {
        return $this->record->factureDefinitive ?? $this->factureProvisoire() ?? $this->record->factureProvisoire;
    }

    private function factureProvisoire(): ?object
    {
        return app(InscriptionFacturationPort::class)->getFactureProvisoire((int) $this->record->id);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Détails de l’inscription')
                ->description('Informations complètes concernant cette inscription')
                ->icon('heroicon-o-document-text')
                ->schema([

                    /*
                     * IDENTITÉ DE L'ÉLÈVE
                     */
                    Grid::make(3)
                        ->schema([
                            ImageEntry::make('eleve.photo')
                                ->label('Photo de l’élève')
                                ->disk('public')
                                ->circular()
                                ->defaultImageUrl(asset('images/student-placeholder.svg'))
                                ->columnSpan(1),

                            TextEntry::make('eleve.nom')
                                ->label('Élève')
                                ->formatStateUsing(
                                    fn ($record) => trim("{$record->eleve->prenom} {$record->eleve->nom}")
                                )
                                ->weight('bold')
                                ->size('lg')
                                ->columnSpan(2),
                        ]),

                    /*
                     * INFORMATIONS DE L'ÉLÈVE
                     */
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('eleve.matricule_permanent')
                                ->label('Matricule')
                                ->placeholder('Non renseigné'),

                            TextEntry::make('eleve.date_naissance')
                                ->label('Date de naissance')
                                ->date('d/m/Y')
                                ->placeholder('Non renseignée'),

                            TextEntry::make('classe.nom')
                                ->label('Classe')
                                ->placeholder('Non renseignée'),

                            TextEntry::make('date_inscription')
                                ->label('Date d’inscription')
                                ->date('d/m/Y'),
                        ]),

                    /*
                     * PARENT / TUTEUR
                     */
                    TextEntry::make('eleve.parentsTuteurs.nom')
                        ->label('Parent(s) / Tuteur(s)')
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->placeholder('Aucun parent ou tuteur renseigné'),

                    /*
                     * FRAIS ET FACTURES
                     */
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('facture_reference')
                                ->label('N° facture provisoire')
                                ->state(fn () => $this->factureProvisoire()?->reference)
                                ->placeholder('—'),

                            TextEntry::make('factureDefinitive.numero')
                                ->label('N° facture définitive')
                                ->placeholder('En attente de versement'),

                            TextEntry::make('facture_envoi')
                                ->label('Envoi de la facture provisoire')
                                ->state(fn () => $this->factureProvisoire()?->envoyee_le ? 'envoyee' : 'en_attente')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): string => match ($state) {
                                    'en_attente' => 'En attente',
                                    'envoyee' => 'Envoyée',
                                    'echec' => 'Échec d’envoi',
                                    default => '—',
                                })
                                ->color(fn (?string $state): string => match ($state) {
                                    'envoyee' => 'success',
                                    'echec' => 'danger',
                                    default => 'warning',
                                }),
                        ]),

                    RepeatableEntry::make('fraisCharges')
                        ->label('Détail des frais')
                        ->schema([
                            TextEntry::make('frais.nom')->label('Frais')->hiddenLabel(),
                            TextEntry::make('montant')->label('Montant')->money('XAF'),
                            TextEntry::make('statut')->label('Statut')->badge()->formatStateUsing(fn (string $state) => $state === 'du' ? 'Dû' : 'Annulé'),
                        ])
                        ->columns(3)
                        ->visible(fn ($record) => $record->fraisCharges->isNotEmpty()),

                    /*
                     * INFORMATIONS FINANCIÈRES
                     */
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('frais_restants')
                                ->label('Reste à payer')
                                ->state(fn ($record) => app(InscriptionFacturationPort::class)->getResteAPayer((int) $record->id))
                                ->money('XAF')
                                ->placeholder('0 XAF'),

                            TextEntry::make('statut')
                                ->label('Statut de l’inscription')
                                ->badge()
                                ->formatStateUsing(
                                    fn (string $state): string => match ($state) {
                                        'en_attente_versement' => 'En attente de versement',
                                        'active' => 'Active',
                                        'annulee' => 'Annulée',
                                        default => $state,
                                    }
                                )
                                ->color(
                                    fn (string $state): string => match ($state) {
                                        'en_attente_versement' => 'warning',
                                        'active' => 'success',
                                        'annulee' => 'danger',
                                        default => 'gray',
                                    }
                                ),
                        ]),

                    RepeatableEntry::make('versements_finances')
                        ->label('Historique des versements')
                        ->state(fn ($record) => app(InscriptionFacturationPort::class)->getVersements((int) $record->id))
                        ->schema([
                            TextEntry::make('numero_recu')->label('Reçu'),
                            TextEntry::make('montant')->label('Montant')->money('XAF'),
                            TextEntry::make('mode')->label('Mode')->badge(),
                            TextEntry::make('statut')->label('Statut')->badge()
                                ->color(fn (string $state) => $state === 'valide' ? 'success' : 'danger')
                                ->formatStateUsing(fn (string $state) => $state === 'valide' ? 'Valide' : 'Annulé'),
                        ])
                        ->columns(4)
                        ->visible(fn ($record) => app(InscriptionFacturationPort::class)->getVersements((int) $record->id) !== []),
                ])
                ->collapsible(false),
        ]);
    }
}
