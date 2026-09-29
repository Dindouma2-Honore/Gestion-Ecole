<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Models\User;
use App\Modules\Socle\Models\ActivityLog;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Audit & sécurité';

    protected static ?string $navigationLabel = 'Audit et traçabilité';

    protected static ?string $modelLabel = 'Journal d’audit';

    protected static ?string $pluralModelLabel = 'Journal d’audit';

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('log_name')->label('Journal')->badge()->sortable(),
                TextColumn::make('description')->label('Opération')->searchable()->wrap(),
                TextColumn::make('subject_type')->label('Type de sujet')->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),
                TextColumn::make('subject_id')->label('Sujet #'),
                TextColumn::make('causer.name')->label('Acteur')->placeholder('Système')->searchable(),
                TextColumn::make('ip_address')->label('Adresse IP')->placeholder('—'),
                TextColumn::make('motif')->label('Motif')->placeholder('—')->limit(60)->wrap(),
                TextColumn::make('properties')->label('Changements')->formatStateUsing(fn ($state): string => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}')->limit(100)->wrap(),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('Type de sujet')
                    ->options(fn (): array => ActivityLog::query()->whereNotNull('subject_type')->distinct()->pluck('subject_type', 'subject_type')->mapWithKeys(fn (string $type, string $key): array => [$key => class_basename($type)])->all()),
                SelectFilter::make('causer_id')
                    ->label('Acteur')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('du')->label('Du'),
                        DatePicker::make('au')->label('Au'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['du'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['au'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ActivityLogResource\Pages\ListActivityLogs::route('/'),
        ];
    }
}
