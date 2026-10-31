<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\ActivityLogResource\Pages;

use App\Modules\Socle\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    public function getTabs(): array
    {
        return [
            'connexions' => Tab::make('Journal des connexions')->icon('heroicon-o-arrow-right-end-on-rectangle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('log_name', 'connexion')),
            'activites' => Tab::make('Journal des activités')->icon('heroicon-o-list-bullet')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('log_name', '!=', 'connexion')),
            'modifications' => Tab::make('Historique des modifications')->icon('heroicon-o-arrows-right-left')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('event', 'updated')),
        ];
    }
}
