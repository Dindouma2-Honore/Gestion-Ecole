<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Filament\Resources\FacturePreinscriptionResource\Pages;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use UnitEnum;

class FacturePreinscriptionResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = FacturePreinscription::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static string|UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $navigationLabel = 'Préinscriptions à contrôler';

    public static function getNavigationBadge(): ?string
    {
        $count = FacturePreinscription::query()->where('statut', 'en_attente_versement')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Inscriptions impayées à contrôler';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('reference')->searchable(),
            Tables\Columns\TextColumn::make('eleve_nom')->label('Élève')->searchable(),
            Tables\Columns\TextColumn::make('parent_nom')->label('Parent'),
            Tables\Columns\TextColumn::make('montant_total')->label('Montant')->money('XAF')->sortable(),
            Tables\Columns\TextColumn::make('montant_versement_prevu')->label('Versement annoncé')->money('XAF')->sortable(),
            Tables\Columns\TextColumn::make('statut')->badge()->color(fn (string $state) => $state === 'payee' ? 'success' : 'warning'),
            Tables\Columns\TextColumn::make('envoyee_le')->label('Facture envoyée')->dateTime('d/m/Y H:i')->placeholder('Non envoyée'),
            Tables\Columns\TextColumn::make('created_at')->label('Émise le')->dateTime('d/m/Y H:i'),
        ])->actions([
            Action::make('voir_facture')
                ->label('Voir la facture PDF')
                ->icon('heroicon-o-document-text')
                ->url(fn (FacturePreinscription $record): ?string => app(DocumentServiceContract::class)
                    ->getUrlTelechargement($record->document_provisoire_id))
                ->openUrlInNewTab()
                ->visible(fn (FacturePreinscription $record): bool => $record->document_provisoire_id !== null),
            Action::make('voir_recu')
                ->label('Voir le reçu PDF')
                ->icon('heroicon-o-receipt-percent')
                ->color('success')
                ->visible(fn (FacturePreinscription $record): bool => $record->statut === 'payee' && $record->paiement_id !== null)
                ->action(function (FacturePreinscription $record) {
                    $recu = app(PaiementServiceContract::class)->getRecuPdf((int) $record->paiement_id);

                    return response()->streamDownload(
                        static fn () => print ($recu['contenu']),
                        $recu['nom'],
                        ['Content-Type' => 'application/pdf'],
                    );
                }),
            Action::make('renvoyer')
                ->label('Renvoyer la facture')
                ->icon('heroicon-o-envelope')
                ->visible(fn (FacturePreinscription $record): bool => $record->statut === 'en_attente_versement' && (Auth::user()?->hasAnyRole(['Comptable', 'Fondateur']) ?? false))
                ->requiresConfirmation()
                ->action(function (FacturePreinscription $record): void {
                    app(FacturePreinscriptionServiceContract::class)->renvoyerFactureProvisoire($record->id);
                    Notification::make()->title('Facture envoyée au parent')->success()->send();
                }),
            Action::make('confirmer')
                ->label('Confirmer le versement')
                ->icon('heroicon-o-check-circle')
                ->color(AmbassadorsDesign::VALIDATION_COLOR)
                ->visible(fn (FacturePreinscription $record): bool => $record->statut === 'en_attente_versement' && (Auth::user()?->hasAnyRole(['Comptable', 'Fondateur']) ?? false))
                ->requiresConfirmation()
                ->modalHeading('Confirmer la réception du paiement')
                ->modalDescription("Cette action validera définitivement l'inscription, générera le reçu et enverra la confirmation au parent.")
                ->modalSubmitActionLabel('Oui, confirmer le paiement')
                ->schema([
                    Select::make('mode')->options(['especes' => 'Espèces', 'bancaire' => 'Virement bancaire', 'mobile_money' => 'Mobile Money'])->required(),
                    TextInput::make('reference_transaction')->label('Référence de transaction'),
                ])
                ->action(function (FacturePreinscription $record, array $data, Component $livewire): void {
                    app(FacturePreinscriptionServiceContract::class)
                        ->confirmerVersement($record->id, $data['mode'], $data['reference_transaction'] ?? null);

                    Notification::make()
                        ->title($data['mode'] === 'especes' ? 'Paiement en espèces confirmé' : 'Versement confirmé')
                        ->body("L'inscription est maintenant validée et le message de confirmation a été envoyé au parent.")
                        ->success()
                        ->persistent()
                        ->send();

                    // The table and its badges must reflect the persisted state
                    // immediately, without requiring a manual browser refresh.
                    $livewire->dispatch('platform-state-updated');
                }),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFacturesPreinscription::route('/')];
    }
}
