<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\UserResource\Pages;

use App\Modules\Socle\Filament\Pages\ManageHabilitations;
use App\Modules\Socle\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('habilitations')
                ->label('Habilitations dynamiques')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(ManageHabilitations::getUrl()),
        ];
    }
}
