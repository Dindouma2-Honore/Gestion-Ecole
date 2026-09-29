<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Modules\Finances\Models\RemiseExoneration;
use App\Modules\Finances\Models\TypeFraisRecurrent;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Throwable;

class RemiseExonerationResource extends Resource
{
    protected static ?string $model = RemiseExoneration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Remise / exonération';

    protected static ?string $pluralModelLabel = 'Remises & exonérations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('eleve_id')
                    ->label('Élève')
                    ->options(fn (): array => collect(app(EleveServiceInterface::class)->getElevesPourSituationFinanciere())
                        ->mapWithKeys(fn (array $eleve): array => [$eleve['id'] => trim($eleve['prenom'].' '.$eleve['nom']).' — '.$eleve['classe']])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('type_frais')
                    ->label('Poste de frais ciblé')
                    ->options(fn (): array => ['scolarite' => 'Frais de scolarité (5 tranches)'] + TypeFraisRecurrent::query()
                        ->where('actif', true)
                        ->whereHas('groupe', fn ($query) => $query->where('code', 'scolarite'))
                        ->where(fn ($query) => $query->whereNull('nature')->orWhere('nature', '!=', 'inscription'))
                        ->orderBy('nom')->pluck('nom')
                        ->unique(fn (string $nom): string => Str::slug($nom))
                        ->mapWithKeys(fn (string $nom): array => [Str::slug($nom) => $nom])
                        ->all())
                    ->helperText('Les remises s’appliquent uniquement à la scolarité, jamais aux frais d’inscription ou divers.')
                    ->searchable()
                    ->required(),
                Select::make('type')
                    ->label('Nature')
                    ->options([
                        'remise_pourcentage' => 'Remise en pourcentage',
                        'remise_montant' => 'Remise en montant fixe',
                        'exoneration_totale' => 'Exonération totale',
                    ])
                    ->live()
                    ->required(),
                TextInput::make('valeur')
                    ->label(fn (Get $get): string => $get('type') === 'remise_pourcentage' ? 'Pourcentage' : 'Montant')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(fn (Get $get): ?int => $get('type') === 'remise_pourcentage' ? 100 : null)
                    ->suffix(fn (Get $get): string => $get('type') === 'remise_pourcentage' ? '%' : 'FCFA')
                    ->visible(fn (Get $get): bool => $get('type') !== 'exoneration_totale')
                    ->required(fn (Get $get): bool => $get('type') !== 'exoneration_totale'),
                Textarea::make('motif')
                    ->required()
                    ->helperText('Obligatoire — journalisé dans l’audit A.4.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('eleve_id')
                    ->label('Élève')
                    ->formatStateUsing(function (int $state): string {
                        try {
                            $eleve = app(EleveServiceInterface::class)->getEleve($state);

                            return "{$eleve['prenom']} {$eleve['nom']}";
                        } catch (Throwable) {
                            return "#{$state}";
                        }
                    }),
                TextColumn::make('type_frais')->label('Type de frais')->badge()->color('info'),
                TextColumn::make('type')
                    ->label('Nature')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'exoneration_totale' => 'danger',
                        'remise_pourcentage', 'remise_montant' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('valeur'),
                TextColumn::make('motif')->limit(50),
                TextColumn::make('approbateur.name')->label('Approuvé par'),
                TextColumn::make('annee_scolaire_id')
                    ->label('Année scolaire')
                    ->formatStateUsing(fn (int $state): string => app(AnneeScolaireServiceContract::class)->getAnneeScolaire($state)?->libelle ?? "#{$state}"),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => RemiseExonerationResource\Pages\ListRemisesExonerations::route('/'),
            'create' => RemiseExonerationResource\Pages\CreateRemiseExoneration::route('/create'),
        ];
    }
}
