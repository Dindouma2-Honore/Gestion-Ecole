<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\ClasseResource\Pages;

use App\Modules\Scolarite\Filament\Resources\ClasseResource;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

class ListClasses extends ListRecords
{
    protected static string $resource = ClasseResource::class;

    #[Url(as: 'affichage')]
    public string $affichage = 'tableau';

    public function usesCardLayout(): bool
    {
        return $this->affichage !== 'tableau';
    }

    public function toggleLayout(): void
    {
        $this->affichage = $this->usesCardLayout() ? 'tableau' : 'cartes';
        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggleLayout')
                ->label(fn (): string => $this->usesCardLayout() ? 'Vue tableau' : 'Vue cartes')
                ->icon(fn (): string => $this->usesCardLayout() ? 'heroicon-o-table-cells' : 'heroicon-o-squares-2x2')
                ->color('gray')
                ->action(fn (): mixed => $this->toggleLayout()),
            ScolariteActions::create()->label('Nouvelle classe'),
        ];
    }

    protected function getEmptyStateActions(): array
    {
        return [
            ScolariteActions::create()->label('Nouvelle classe'),
        ];
    }
}
