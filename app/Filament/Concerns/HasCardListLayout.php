<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Actions;
use Livewire\Attributes\Url;

trait HasCardListLayout
{
    #[Url(as: 'affichage')]
    public string $affichage = 'cartes';

    public function usesCardLayout(): bool
    {
        return $this->affichage !== 'tableau';
    }

    public function toggleLayout(): void
    {
        $this->affichage = $this->usesCardLayout() ? 'tableau' : 'cartes';
        $this->resetTable();
    }

    protected function cardLayoutAction(): Actions\Action
    {
        return Actions\Action::make('toggleLayout')
            ->label(fn (): string => $this->usesCardLayout() ? 'Vue tableau' : 'Vue cartes')
            ->icon(fn (): string => $this->usesCardLayout() ? 'heroicon-o-table-cells' : 'heroicon-o-squares-2x2')
            ->color('gray')
            ->action('toggleLayout');
    }
}
