<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\EleveResource\Pages;

use App\Filament\Concerns\HasCardListLayout;
use App\Modules\Scolarite\Filament\Resources\EleveResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

class ListEleves extends ListRecords
{
    use HasCardListLayout;

    protected static string $resource = EleveResource::class;

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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggleLayout')
                ->label(fn (): string => $this->usesCardLayout()
                    ? 'Vue tableau'
                    : 'Vue cartes'
                )
                ->icon(fn (): string => $this->usesCardLayout()
                    ? 'heroicon-o-table-cells'
                    : 'heroicon-o-squares-2x2'
                )
                ->color('gray'),

            Actions\CreateAction::make()
                ->label('Nouvel élève'),
        ];
    }
}
