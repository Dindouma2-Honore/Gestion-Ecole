<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Contracts\DetectionAbsenceServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\AnomalieAppelResource\Pages;
use App\Modules\Pedagogie\Models\AnomalieAppel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AnomalieAppelResource extends Resource
{
    protected static ?string $model = AnomalieAppel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Anomalies d\'appel';

    protected static ?string $modelLabel = 'anomalie d\'appel';

    protected static ?string $pluralModelLabel = 'anomalies d\'appel';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('seance.emploiDuTemps.matiere.nom')->label('Matière'),
                Tables\Columns\TextColumn::make('seance.date_seance')->label('Date')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('detectee_le')->label('Détectée le')->dateTime('d/m/Y H:i'),
                Tables\Columns\IconColumn::make('notifiee')->label('Notifiée')->boolean(),
                Tables\Columns\IconColumn::make('resolue')->label('Résolue')->boolean(),
            ])
            ->defaultSort('detectee_le', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('resolue')->label('Résolue'),
            ])
            ->actions([
                Action::make('marquer_resolue')
                    ->label('Marquer résolue')
                    ->icon('heroicon-o-check')
                    ->visible(fn (AnomalieAppel $record) => ! $record->resolue)
                    ->action(fn (AnomalieAppel $record) => app(DetectionAbsenceServiceInterface::class)
                        ->marquerResolue($record->id)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnomalieAppels::route('/'),
        ];
    }
}
