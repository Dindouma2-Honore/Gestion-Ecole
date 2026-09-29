<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\EvenementResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    protected static ?string $title = 'Participants';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('participant_id')
            ->columns([
                Tables\Columns\TextColumn::make('participant_type')->label('Type'),
                Tables\Columns\TextColumn::make('participant_id')->label('ID'),
                Tables\Columns\IconColumn::make('autorisation_parentale_recue')
                    ->label('Autorisation reçue')
                    ->boolean(),
            ]);
    }
}
