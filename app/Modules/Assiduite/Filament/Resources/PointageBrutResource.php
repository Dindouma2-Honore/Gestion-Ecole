<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Filament\Resources;

use App\Modules\Assiduite\Filament\Resources\PointageBrutResource\Pages;
use App\Modules\Assiduite\Models\PointageBrut;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PointageBrutResource extends Resource
{
    protected static ?string $model = PointageBrut::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'pointage brut';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([Tables\Columns\TextColumn::make('horodatage')->label('Date/heure')->dateTime('d/m/Y H:i:s')->sortable(), Tables\Columns\TextColumn::make('identifiant_biometrique')->label('Identifiant')->searchable(), Tables\Columns\TextColumn::make('terminal_id')->label('Terminal')->searchable(), Tables\Columns\TextColumn::make('type_pointage')->label('Type')->badge(), Tables\Columns\TextColumn::make('source')->badge(), Tables\Columns\TextColumn::make('statut_import')->label('Import')->badge()])->defaultSort('horodatage', 'desc')->defaultPaginationPageOption(5);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPointagesBruts::route('/')];
    }
}
