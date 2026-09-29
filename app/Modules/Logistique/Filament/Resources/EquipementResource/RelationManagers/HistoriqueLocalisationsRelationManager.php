<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\EquipementResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HistoriqueLocalisationsRelationManager extends RelationManager
{
    protected static string $relationship = 'historiqueLocalisations';

    protected static ?string $title = 'Historique des localisations';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date_deplacement')
            ->columns([
                Tables\Columns\TextColumn::make('ancienne_salle_id')->label('Ancienne salle (ID)')->placeholder('—'),
                Tables\Columns\TextColumn::make('nouvelle_salle_id')->label('Nouvelle salle (ID)'),
                Tables\Columns\TextColumn::make('date_deplacement')->label('Date')->date(),
            ])
            ->defaultSort('date_deplacement', 'desc');
    }
}
