<?php

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Models\BulletinPaie;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employe.nom_complet')->label('Employé')->sortable()->searchable(),
                TextColumn::make('mois')->sortable(),
                TextColumn::make('annee')->sortable(),
                TextColumn::make('salaire_base')->money('XAF')->sortable(),
                TextColumn::make('total_primes')->money('XAF')->sortable(),
                TextColumn::make('total_heures_supplementaires')->label('Heures supplémentaires')->money('XAF'),
                TextColumn::make('total_retenues')->money('XAF')->sortable(),
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
                TextColumn::make('date_paiement')->date()->sortable(),
            ])
            ->actions([
                EditAction::make(),
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
                Action::make('valider')->label('Valider')->color('success')
                    ->visible(fn (BulletinPaie $record): bool => $record->statut === 'calcule')
                    ->action(fn (BulletinPaie $record) => app(PaieServiceContract::class)->validerBulletin($record->id, (int) Auth::id())),
                Action::make('payer')->label('Marquer payé')->color('primary')
                    ->visible(fn (BulletinPaie $record): bool => $record->statut === 'valide')
                    ->action(fn (BulletinPaie $record) => app(PaieServiceContract::class)->marquerPaye($record->id, now())),
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
}
