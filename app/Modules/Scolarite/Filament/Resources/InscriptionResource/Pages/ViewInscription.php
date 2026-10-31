<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages;

use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
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
use Filament\Schemas\Components\View;
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
                    try {
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
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title('Erreur lors de l’encaissement')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('transfererClasse')
                ->label('Transférer de classe')
                ->icon('heroicon-o-arrows-right-left')
                ->color('primary')
                ->visible(fn (): bool => ! in_array($this->record->statut, ['annulee'], true))
                ->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('form'))
                ->modalHeading('Transfert de classe & Recalcul financier')
                ->modalDescription('Sélectionnez la nouvelle classe pour prévisualiser immédiatement l’impact financier et la réallocation des tranches avant confirmation.')
                ->modalSubmitActionLabel('Confirmer le transfert')
                ->form([
                    Select::make('nouvelle_classe_id')
                        ->label('Nouvelle classe de destination')
                        ->options(fn (): array => Classe::query()
                            ->where('annee_scolaire_id', $this->record->annee_scolaire_id)
                            ->where('statut', 'active')
                            ->where('id', '!=', $this->record->classe_id)
                            ->orderBy('nom')
                            ->pluck('nom', 'id')
                            ->all()
                        )
                        ->searchable()
                        ->required()
                        ->live(),

                    Placeholder::make('comparatif_transfert')
                        ->label('Comparatif financier & Tranches')
                        ->visible(fn ($get) => filled($get('nouvelle_classe_id')))
                        ->content(function ($get) {
                            $nouvelleClasseId = (int) $get('nouvelle_classe_id');
                            if (! $nouvelleClasseId) {
                                return null;
                            }
                            $simulation = app(InscriptionFacturationPort::class)->simulerTransfertFinancier((int) $this->record->id, $nouvelleClasseId);

                            return view('scolarite::filament.components.transfert-comparatif-table', [
                                'simulation' => $simulation,
                            ]);
                        }),

                    Textarea::make('motif')
                        ->label('Motif du transfert')
                        ->placeholder('Ex : Changement de filière, rééquilibrage pédagogique, convenance parentale...')
                        ->required(),

                    DatePicker::make('date_transfert')
                        ->label('Date d’effet du transfert')
                        ->default(now()->toDateString())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(InscriptionServiceInterface::class)->transfererClasse(
                            inscriptionId: $this->record->id,
                            nouvelleClasseId: (int) $data['nouvelle_classe_id'],
                            motif: $data['motif'],
                            dateTransfert: (string) $data['date_transfert'],
                        );

                        Notification::make()
                            ->title('Transfert de classe effectué avec succès !')
                            ->body('L’élève a été réaffecté et la scolarité a été automatiquement recalculée.')
                            ->success()
                            ->send();

                        $this->record->refresh();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title('Erreur lors du transfert')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('viserAnnulation')
                ->label('Viser l’annulation (Comptabilité)')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Visa comptable préalable à l’annulation')
                ->modalDescription('Contrôle à 4 yeux — 1er œil : En tant que Comptable, attestez de la vérification financière (quittances, paiements perçus) avant transmission au Fondateur.')
                ->modalSubmitActionLabel('Apposer le visa comptable')
                ->visible(fn (): bool => $this->record->statut === 'en_attente_annulation' && ! $this->record->aVisaComptable() && InscriptionResource::estComptable())
                ->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                ->form([
                    Textarea::make('notes')
                        ->label('Observations financières / Notes de visa')
                        ->placeholder('Ex : Versements vérifiés, quittances acquittées, aucun avoir bloquant.')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(InscriptionServiceInterface::class)->viserAnnulation($this->record->id, $data['notes']);
                        Notification::make()
                            ->title('Visa comptable apposé avec succès')
                            ->body('La demande a été visée et transmise au Fondateur pour validation définitive.')
                            ->success()
                            ->send();

                        $this->record->refresh();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Erreur lors du visa')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('validerAnnulation')
                ->label('Valider l’annulation (Fondateur)')
                ->icon('heroicon-o-check-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Valider l’annulation de l’inscription')
                ->modalDescription('Contrôle à 4 yeux — 2e œil : Le visa de la comptabilité a été accordé. Cette décision finale libère la place de l’élève et clôture son inscription.')
                ->modalSubmitActionLabel('Confirmer l’annulation définitive')
                ->visible(fn (): bool => $this->record->statut === 'en_attente_annulation' && $this->record->aVisaComptable() && InscriptionResource::estFondateur())
                ->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                ->form([
                    Textarea::make('motif')
                        ->label('Motif / Décision finale du Fondateur')
                        ->default(fn (): ?string => $this->record->motif_annulation)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(InscriptionServiceInterface::class)->validerAnnulation($this->record->id, $data['motif']);
                        Notification::make()
                            ->title('Annulation validée avec succès par le Fondateur')
                            ->body('L’inscription a été annulée et l’élève quitte la liste des inscrits actifs.')
                            ->success()
                            ->send();
                        $this->record->refresh();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Erreur lors de la validation')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('rejeterAnnulation')
                ->label('Rejeter la demande d’annulation')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Rejeter la demande d’annulation')
                ->modalDescription('L’inscription restera active et la place de l’élève sera conservée.')
                ->visible(fn (): bool => $this->record->statut === 'en_attente_annulation' && InscriptionResource::estFondateur())
                ->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                ->form([
                    Textarea::make('motif')->label('Motif du rejet')->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(InscriptionServiceInterface::class)->rejeterAnnulation($this->record->id, $data['motif']);
                        Notification::make()
                            ->title('Demande d’annulation rejetée')
                            ->info()
                            ->send();
                        $this->record->refresh();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Erreur lors du rejet')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('demanderAnnulation')
                ->label('Demander l’annulation')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Demande d’annulation d’inscription')
                ->modalDescription('L’annulation requiert un contrôle à 4 yeux (visa préalable de la Comptabilité puis validation finale du Fondateur).')
                ->modalSubmitActionLabel('Soumettre la demande')
                ->visible(fn (): bool => ! in_array($this->record->statut, ['annulee', 'en_attente_annulation'], true))
                ->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                ->form([
                    Textarea::make('motif')->label('Motif de la demande d’annulation')->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(InscriptionServiceInterface::class)->demanderAnnulation($this->record->id, $data['motif']);
                        $estComptable = InscriptionResource::estComptable();
                        Notification::make()
                            ->title($estComptable ? 'Demande visée et transmise au Fondateur' : 'Demande transmise à la Comptabilité')
                            ->body($estComptable
                                ? 'Votre visa comptable a été enregistré. La demande attend la validation du Fondateur.'
                                : 'La demande a été transmise à la Comptabilité pour visa financier préalable.')
                            ->warning()
                            ->send();
                        $this->record->refresh();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Erreur lors de la demande')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
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
            View::make('scolarite::filament.pages.inscription-details-table')
                ->viewData(fn (): array => [
                    'record' => $this->record->loadMissing([
                        'eleve.parentsTuteurs',
                        'classe',
                        'anneeScolaire',
                        'demandeurAnnulation',
                        'viseurAnnulation',
                        'validateurAnnulation',
                        'transferts.classeOrigine',
                        'transferts.classeDestination',
                        'transferts.auteur',
                    ]),
                    'dossier' => app(InscriptionFacturationPort::class)->getDossierFinancierComplet((int) $this->record->id),
                    'factureProvisoire' => $this->factureProvisoire(),
                    'reste' => app(InscriptionFacturationPort::class)->getResteAPayer((int) $this->record->id),
                    'versements' => app(InscriptionFacturationPort::class)->getVersements((int) $this->record->id),
                ])
                ->columnSpanFull(),
        ]);
    }

    private function legacyInfolist(Schema $schema): Schema
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
