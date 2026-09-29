<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Models\User;
use App\Modules\Finances\Contracts\GestionDepenseServiceContract;
use App\Modules\Finances\Filament\Resources\DepenseResource\Pages;
use App\Modules\Finances\Models\Depense;
use App\Modules\Finances\Models\RubriqueDepense;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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

class DepenseResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('interface.expenses');
    }

    protected static ?string $model = Depense::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Dépense';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('rubrique_depense_id')->label('Rubrique')
                ->options(fn (): array => RubriqueDepense::query()->where('active', true)->orderBy('nom')->pluck('nom', 'id')->all())
                ->required(),
            TextInput::make('libelle')->required()->maxLength(180),
            TextInput::make('montant')->numeric()->minValue(1)->suffix('FCFA')->required(),
            Textarea::make('motif')->required()->maxLength(1000),
            FileUpload::make('justificatif')->disk('public')->directory('finances/depenses')->maxSize(5120),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('rubrique.nom')->label('Rubrique')->searchable()->sortable(),
            TextColumn::make('libelle')->searchable(),
            TextColumn::make('montant')->money('XAF')->sortable(),
            TextColumn::make('date_depense')->date()->sortable(),
            TextColumn::make('statut')->badge()->color(fn (string $state): string => match ($state) {
                'payee' => 'success', 'validee' => 'info', 'rejetee' => 'danger',
                'en_attente_validation' => 'warning', default => 'gray',
            }),
            TextColumn::make('createur.name')->label('Créée par'),
        ])->filters([
            SelectFilter::make('statut')->options([
                'en_attente_validation' => 'En attente', 'validee' => 'Validée', 'payee' => 'Payée', 'rejetee' => 'Rejetée',
            ]),
        ])->recordActions([
            Action::make('valider')->color(AmbassadorsDesign::VALIDATION_COLOR)->icon('heroicon-o-check-circle')
                ->schema([Textarea::make('motif')->required()])
                ->visible(fn (Depense $record): bool => $record->statut === 'en_attente_validation' && self::estFondateur())
                ->action(fn (Depense $record, array $data) => app(GestionDepenseServiceContract::class)->valider($record->id, $data['motif'])),
            Action::make('payer')->color('success')->icon('heroicon-o-banknotes')->requiresConfirmation()
                ->visible(fn (Depense $record): bool => $record->statut === 'validee' && self::peutGerer())
                ->action(fn (Depense $record) => app(GestionDepenseServiceContract::class)->marquerPayee($record->id)),
        ]);
    }

    public static function canAccess(): bool
    {
        return self::peutGerer();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function peutGerer(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole(['Fondateur', 'Comptable']) ?? false;
    }

    private static function estFondateur(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('Fondateur') ?? false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDepenses::route('/'), 'create' => Pages\CreateDepense::route('/create')];
    }
}
