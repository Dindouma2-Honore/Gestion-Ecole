<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Filament\Support\ModuleAccess;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use App\Modules\Scolarite\Filament\Resources\TransfertAffectationResource\Pages;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\TransfertAffectation;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TransfertAffectationResource extends Resource
{
    protected static ?string $model = TransfertAffectation::class;

    protected static string|UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Affectations / Transferts';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'transfert ou affectation';

    protected static ?string $pluralModelLabel = 'transferts et affectations';

    public static function canAccess(): bool
    {
        return ModuleAccess::canAccessModule(auth()->user(), 'Scolarite');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('inscription_id')
                ->label('Élève actuellement affecté')
                ->options(fn (): array => Inscription::query()
                    ->with(['eleve', 'classe'])
                    ->whereNotIn('statut', ['annulee'])
                    ->latest('date_inscription')
                    ->get()
                    ->mapWithKeys(fn (Inscription $inscription): array => [
                        $inscription->id => trim("{$inscription->eleve?->nom} {$inscription->eleve?->prenom}")
                            .' — '.($inscription->classe?->nom ?? 'Sans classe'),
                    ])->all())
                ->searchable()
                ->live()
                ->required(),
            Placeholder::make('classe_actuelle')
                ->label('Classe actuelle')
                ->content(fn ($get): string => Inscription::query()->with('classe')->find($get('inscription_id'))?->classe?->nom ?? '—'),
            Select::make('classe_destination_id')
                ->label('Nouvelle classe')
                ->options(function ($get): array {
                    $inscription = Inscription::query()->find($get('inscription_id'));
                    if (! $inscription) {
                        return [];
                    }

                    return Classe::query()
                        ->where('annee_scolaire_id', $inscription->annee_scolaire_id)
                        ->whereKeyNot($inscription->classe_id)
                        ->where('statut', 'active')
                        ->orderBy('nom')
                        ->pluck('nom', 'id')
                        ->all();
                })
                ->searchable()
                ->required(),
            DatePicker::make('date_transfert')->label('Date du transfert')->default(today())->maxDate(today())->required(),
            Textarea::make('motif')->label('Motif du transfert')->rows(3)->required()->maxLength(1000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        ScolariteActions::configureTable($table, 'view');

        return $table
            ->columns([
                TextColumn::make('eleve.nom')->label('Élève')->formatStateUsing(fn ($record): string => trim("{$record->eleve?->nom} {$record->eleve?->prenom}"))->searchable(),
                TextColumn::make('classeOrigine.nom')->label('Classe d’origine'),
                TextColumn::make('classeDestination.nom')->label('Nouvelle classe'),
                TextColumn::make('date_transfert')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('auteur.name')->label('Effectué par')->placeholder('—'),
                TextColumn::make('motif')->label('Motif')->limit(35)->tooltip(fn ($record): string => $record->motif),
            ])
            ->actions([
                ScolariteActions::view(),
            ])
            ->defaultSort('date_transfert', 'desc')
            ->paginated([10]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransfertsAffectations::route('/'),
            'create' => Pages\CreateTransfertAffectation::route('/create'),
        ];
    }
}
