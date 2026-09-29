<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Filament\Support\NiveauScopeSelect;
use App\Modules\Socle\Models\JourFerie;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JourFerieResource extends Resource
{
    protected static ?string $model = JourFerie::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Années & périodes';

    protected static ?string $modelLabel = 'Jour férié';

    protected static ?string $pluralModelLabel = 'Jours fériés';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('libelle')->label('Libellé')->required(),
            DatePicker::make('date')->required()->native(false),
            NiveauScopeSelect::make(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('date')->date('d/m/Y')->sortable(), TextColumn::make('libelle')->searchable(),
            TextColumn::make('niveau.nom')->label('Niveau')->placeholder('Tous les niveaux'),
        ])->defaultSort('date');
    }

    public static function getPages(): array
    {
        return ['index' => JourFerieResource\Pages\ListJoursFeries::route('/'), 'create' => JourFerieResource\Pages\CreateJourFerie::route('/create'), 'edit' => JourFerieResource\Pages\EditJourFerie::route('/{record}/edit')];
    }
}
