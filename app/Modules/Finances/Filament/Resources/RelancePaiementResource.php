<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Modules\Finances\Models\RelancePaiement;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class RelancePaiementResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = RelancePaiement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $navigationLabel = 'Recouvrement';

    protected static ?string $modelLabel = 'Relance de paiement';

    protected static ?string $pluralModelLabel = 'Relances de paiement';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('eleve_id')->label('Élève')->formatStateUsing(function (int $state): string {
                    try {
                        $eleve = app(EleveServiceInterface::class)->getEleve($state);

                        return "{$eleve['prenom']} {$eleve['nom']}";
                    } catch (Throwable) {
                        return "#{$state}";
                    }
                }),
                TextColumn::make('niveau_relance')
                    ->label('Niveau')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 3 => 'danger',
                        $state === 2 => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('canal')->badge(),
                TextColumn::make('reste_a_payer_constate')->label('Reste à payer')->money('XAF')->sortable(),
                TextColumn::make('date_relance')->dateTime()->sortable(),
            ])
            ->defaultSort('reste_a_payer_constate', 'desc')
            ->filters([
                SelectFilter::make('canal')->options(['sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'lettre' => 'Lettre']),
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
            'index' => RelancePaiementResource\Pages\ListRelancesPaiement::route('/'),
        ];
    }
}
