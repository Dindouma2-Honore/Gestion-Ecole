<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Filament\Resources\RendezVousResource\Pages;
use App\Modules\Communication\Models\RendezVous;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class RendezVousResource extends Resource
{
    protected static ?string $model = RendezVous::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'Rendez-vous';

    protected static ?string $modelLabel = 'rendez-vous';

    protected static ?string $pluralModelLabel = 'rendez-vous';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label('Parent / Tuteur')
                    ->options(fn (): array => \Illuminate\Support\Facades\DB::table('parents_tuteurs')
                        ->get()
                        ->mapWithKeys(fn ($p) => [$p->id => trim("{$p->prenom} {$p->nom}")])
                        ->toArray()
                    )
                    ->searchable()
                    ->required(),

                Select::make('responsable_id')
                    ->label('Responsable (Staff)')
                    ->relationship('responsable', 'name')
                    ->searchable()
                    ->required(),

                TextInput::make('motif')
                    ->required()
                    ->maxLength(255),

                DateTimePicker::make('date_heure_demandee')
                    ->label('Date/Heure Demandée')
                    ->required(),

                DateTimePicker::make('date_heure_confirmee')
                    ->label('Date/Heure Confirmée'),

                Select::make('statut')
                    ->options([
                        'demande' => 'Demandé',
                        'confirme' => 'Confirmé',
                        'effectue' => 'Effectué',
                        'annule' => 'Annulé',
                    ])
                    ->default('demande')
                    ->required(),

                Textarea::make('compte_rendu')
                    ->label('Compte rendu')
                    ->rows(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('parent')
                    ->label('Parent')
                    ->formatStateUsing(fn ($record) => $record->parent ? trim("{$record->parent->prenom} {$record->parent->nom}") : '-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('responsable.name')->label('Responsable')->searchable(),
                Tables\Columns\TextColumn::make('motif')->searchable(),
                Tables\Columns\TextColumn::make('date_heure_demandee')->label('Demandé pour')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('date_heure_confirmee')->label('Confirmé pour')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirme', 'effectue' => 'success',
                        'demande' => 'warning',
                        'annule' => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'demande' => 'Demandé',
                        'confirme' => 'Confirmé',
                        'effectue' => 'Effectué',
                        'annule' => 'Annulé',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRendezVous::route('/'),
            'create' => Pages\CreateRendezVous::route('/create'),
            'edit' => Pages\EditRendezVous::route('/{record}/edit'),
        ];
    }
}
