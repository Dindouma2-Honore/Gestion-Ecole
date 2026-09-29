<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Models\User;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Filament\Resources\PaiementResource\Pages;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Paiement scolaire';

    protected static ?string $pluralModelLabel = 'Paiements scolaires';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('eleve_id')->label('Élève (ID)')->numeric()->live(onBlur: true)->required(),
            Placeholder::make('reste_a_payer')
                ->label('Reste à payer')
                ->content(function ($get): string {
                    $eleveId = (int) $get('eleve_id');
                    if ($eleveId <= 0) {
                        return 'Sélectionnez un élève.';
                    }

                    try {
                        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                        $reste = app(PaiementServiceContract::class)->getResteAPayer($eleveId, $anneeId);

                        return number_format($reste, 0, ',', ' ').' FCFA';
                    } catch (Throwable) {
                        return 'Montant indisponible pour cet élève.';
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

    public static function table(Table $table): Table
    {
        return $table
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
            ]);
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
