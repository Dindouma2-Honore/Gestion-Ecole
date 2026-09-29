<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Models\User;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Filament\Resources\AvanceSalaireResource\Pages;
use App\Modules\RH\Models\AvanceSalaire;
use App\Modules\RH\Models\Employe;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
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

class AvanceSalaireResource extends Resource
{
    protected static ?string $model = AvanceSalaire::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources Humaines';

    protected static ?string $modelLabel = 'Avance sur salaire';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employe_id')->label('Personnel')
                ->options(fn (): array => Employe::query()->where('statut', 'actif')->orderBy('nom')->get()
                    ->mapWithKeys(fn (Employe $employe): array => [$employe->id => $employe->nom_complet])->all())
                ->searchable()->required(),
            TextInput::make('montant')->numeric()->minValue(1)->suffix('FCFA')->required()
                ->helperText('Plafond normal : 30 % du salaire de base.'),
            Textarea::make('motif')->required(),
            Checkbox::make('derogation_plafond')->label('Dérogation exceptionnelle au plafond de 30 %')
                ->live()->visible(fn (): bool => self::estFondateur()),
            Textarea::make('motif_derogation')->label('Motif de la dérogation')
                ->visible(fn ($get): bool => (bool) $get('derogation_plafond'))
                ->required(fn ($get): bool => (bool) $get('derogation_plafond')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('employe.nom_complet')->label('Personnel')->searchable(['nom', 'prenom']),
            TextColumn::make('montant')->money('XAF')->sortable(),
            TextColumn::make('montant_deja_deduit')->label('Déjà remboursé')->money('XAF'),
            TextColumn::make('statut')->badge()->color(fn (string $state): string => match ($state) {
                'approuvee' => 'info', 'remboursee' => 'success', 'rejetee' => 'danger', default => 'warning',
            }),
            TextColumn::make('date_demande')->date()->sortable(),
        ])->filters([
            SelectFilter::make('statut')->options([
                'demande' => 'En attente', 'approuvee' => 'Approuvée', 'remboursee' => 'Remboursée', 'rejetee' => 'Rejetée',
            ]),
        ])->recordActions([
            Action::make('valider')->label('Valider et décaisser')->icon('heroicon-o-banknotes')
                ->color(AmbassadorsDesign::VALIDATION_COLOR)
                ->schema([Textarea::make('motif')->label('Motif de validation')->required()])
                ->visible(fn (AvanceSalaire $record): bool => $record->statut === 'demande' && self::estFondateur())
                ->action(fn (AvanceSalaire $record, array $data) => app(PaieServiceContract::class)->validerAvance($record->id, $data['motif'])),
        ]);
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole(['Fondateur', 'Comptable', 'Responsable RH']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function estFondateur(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('Fondateur') ?? false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAvancesSalaire::route('/'), 'create' => Pages\CreateAvanceSalaire::route('/create')];
    }
}
