<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Modules\Finances\Models\MouvementCaisse;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MouvementCaisseResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('interface.cash');
    }

    protected static ?string $model = MouvementCaisse::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lock-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $navigationLabel = 'Caisse';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Mouvement de caisse';

    protected static ?string $pluralModelLabel = 'Mouvements de caisse';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session.date_session')->label('Session')->date()->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'encaissement' ? 'success' : 'danger'),
                TextColumn::make('montant')->money('XAF')->sortable(),
                TextColumn::make('source_type')->label('Origine')->formatStateUsing(fn (?string $state): string => match (class_basename($state)) {
                    'Paiement' => 'Paiement scolaire',
                    '' => 'Manuel',
                    default => class_basename($state),
                }),
                TextColumn::make('justificatif')->limit(40)->placeholder('—'),
                TextColumn::make('created_at')->label('Enregistré le')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')->options(['encaissement' => 'Encaissement', 'decaissement' => 'Décaissement']),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => MouvementCaisseResource\Pages\ListMouvementsCaisse::route('/'),
        ];
    }
}
