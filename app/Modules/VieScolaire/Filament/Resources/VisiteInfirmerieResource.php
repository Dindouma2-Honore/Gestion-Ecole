<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources;

use App\Modules\VieScolaire\Filament\Resources\VisiteInfirmerieResource\Pages;
use App\Modules\VieScolaire\Models\VisiteInfirmerie;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class VisiteInfirmerieResource extends Resource
{
    protected static ?string $model = VisiteInfirmerie::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';
    protected static string|UnitEnum|null $navigationGroup = 'Vie scolaire';
    protected static ?string $navigationLabel = 'Visites infirmerie';
    protected static ?string $modelLabel = 'visite';
    protected static ?string $pluralModelLabel = 'visites infirmerie';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('eleve_id')->label('ID élève')->numeric()->required(),
            Textarea::make('motif')->label('Motif')->required(),
            Textarea::make('soins_prodigues')->label('Soins prodigués'),
            TextInput::make('medicament_administre')->label('Médicament administré'),
            Select::make('gravite')->label('Gravité')->options([
                'mineure' => 'Mineure',
                'moderee' => 'Modérée',
                'grave' => 'Grave',
            ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('eleve_id')->label('Élève')->searchable(),
            Tables\Columns\TextColumn::make('date_heure')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
            Tables\Columns\TextColumn::make('motif')->label('Motif')->limit(50),
            Tables\Columns\TextColumn::make('gravite')->label('Gravité')->badge(),
            Tables\Columns\IconColumn::make('evacuation_necessaire')->label('Évacuation')->boolean(),
            Tables\Columns\IconColumn::make('parent_notifie')->label('Parent notifié')->boolean(),
        ])->actions([
            EditAction::make(),
        ])->filters([
            Tables\Filters\SelectFilter::make('gravite')->options([
                'mineure' => 'Mineure',
                'moderee' => 'Modérée',
                'grave' => 'Grave',
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisitesInfirmerie::route('/'),
            'create' => Pages\CreateVisiteInfirmerie::route('/create'),
            'edit' => Pages\EditVisiteInfirmerie::route('/{record}/edit'),
        ];
    }
}
