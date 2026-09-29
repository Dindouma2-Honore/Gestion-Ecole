<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Models\User;
use App\Modules\Finances\Contracts\BudgetServiceContract;
use App\Modules\Finances\Filament\Resources\BudgetResource\Pages;
use App\Modules\Finances\Models\Budget;
use App\Modules\Finances\Models\BudgetCategorie;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BudgetResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Budget::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $modelLabel = 'Budget prévisionnel';

    protected static ?string $pluralModelLabel = 'Budgets prévisionnels';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('annee_scolaire_id')
                ->label('Année scolaire')
                ->options(fn (): array => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())
                    ->pluck('libelle', 'id')->all())
                ->required(),
            Repeater::make('lignes')
                ->label('Lignes budgétaires')
                ->schema([
                    Select::make('categorie_id')
                        ->label('Catégorie')
                        ->options(fn (): array => BudgetCategorie::query()->orderBy('type')->orderBy('nom')->get()
                            ->mapWithKeys(fn (BudgetCategorie $categorie): array => [
                                $categorie->id => ucfirst($categorie->type).' — '.$categorie->nom,
                            ])->all())
                        ->createOptionForm([
                            TextInput::make('nom')->required()->maxLength(100),
                            Select::make('type')->options(['recette' => 'Recette', 'depense' => 'Dépense'])->required(),
                        ])
                        ->createOptionUsing(fn (array $data): int => BudgetCategorie::create($data)->id)
                        ->distinct()
                        ->required(),
                    TextInput::make('montant_prevu')->label('Montant prévu')->numeric()->minValue(0.01)->suffix('FCFA')->required(),
                ])
                ->minItems(1)
                ->columns(2)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('annee_scolaire_id')
                    ->label('Année scolaire')
                    ->formatStateUsing(fn (int $state): string => app(AnneeScolaireServiceContract::class)
                        ->getAnneeScolaire($state)?->libelle ?? "#{$state}"),
                TextColumn::make('lignes_count')->counts('lignes')->label('Lignes'),
                TextColumn::make('lignes_sum_montant_prevu')->sum('lignes', 'montant_prevu')->label('Total prévu')->money('XAF'),
                TextColumn::make('statut')->badge()->color(fn (string $state): string => match ($state) {
                    'brouillon' => 'warning',
                    'valide' => 'success',
                    'cloture' => 'gray',
                    default => 'gray',
                }),
                TextColumn::make('validateur.name')->label('Validé par'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color(AmbassadorsDesign::VALIDATION_COLOR)
                    ->requiresConfirmation()
                    ->visible(function (Budget $record): bool {
                        /** @var User|null $user */
                        $user = Auth::user();

                        return $record->statut === 'brouillon'
                            && ($user?->hasAnyRole(['Fondateur', 'Comptable']) ?? false);
                    })
                    ->action(fn (Budget $record) => app(BudgetServiceContract::class)
                        ->validerBudget($record->id, (int) Auth::id())),
            ]);
    }

    public static function canEdit(Model $record): bool
    {
        return $record->statut === 'brouillon';
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBudgets::route('/'),
            'create' => Pages\CreateBudget::route('/create'),
        ];
    }
}
