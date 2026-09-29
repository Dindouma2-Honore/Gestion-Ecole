<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Models\CreneauHoraire;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class CreneauHoraireResource extends Resource
{
    protected static ?string $model = CreneauHoraire::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Créneaux horaires';

    protected static ?string $modelLabel = 'créneau horaire';

    protected static ?string $pluralModelLabel = 'créneaux horaires';

    private const JOURS = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('jour_semaine')
                    ->label('Jour')
                    ->options(self::JOURS)
                    ->required(),

                TimePicker::make('heure_debut')
                    ->label('Heure de début')
                    ->seconds(false)
                    ->required(),

                TimePicker::make('heure_fin')
                    ->label('Heure de fin')
                    ->seconds(false)
                    ->required()
                    ->after('heure_debut'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('jour_semaine')
                    ->label('Jour')
                    ->formatStateUsing(fn (int $state): string => self::JOURS[$state] ?? (string) $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('heure_debut')
                    ->label('Début')
                    ->time('H:i'),

                Tables\Columns\TextColumn::make('heure_fin')
                    ->label('Fin')
                    ->time('H:i'),
            ])
            ->defaultSort('jour_semaine')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => CreneauHoraireResource\Pages\ListCreneauxHoraires::route('/'),
            'create' => CreneauHoraireResource\Pages\CreateCreneauHoraire::route('/create'),
            'edit' => CreneauHoraireResource\Pages\EditCreneauHoraire::route('/{record}/edit'),
        ];
    }
}