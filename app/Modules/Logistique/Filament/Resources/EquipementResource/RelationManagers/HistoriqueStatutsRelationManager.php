<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\EquipementResource\RelationManagers;

use App\Modules\Logistique\Enums\StatutOperationnelMateriel;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HistoriqueStatutsRelationManager extends RelationManager
{
    protected static string $relationship = 'historiqueStatuts';

    protected static ?string $title = 'Historique des statuts opérationnels';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nouveau_statut')
            ->columns([
                Tables\Columns\TextColumn::make('ancien_statut')
                    ->label('Ancien statut')
                    ->formatStateUsing(fn ($state) => $state ? (StatutOperationnelMateriel::tryFrom($state)?->label() ?? $state) : '—'),
                Tables\Columns\TextColumn::make('nouveau_statut')
                    ->label('Nouveau statut')
                    ->badge()
                    ->formatStateUsing(fn ($state) => StatutOperationnelMateriel::tryFrom($state)?->label() ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'en_stock' => 'success',
                        'affecte' => 'info',
                        'en_maintenance' => 'warning',
                        'loue' => 'primary',
                        'reforme' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('motif')->label('Motif')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('Date de changement')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
