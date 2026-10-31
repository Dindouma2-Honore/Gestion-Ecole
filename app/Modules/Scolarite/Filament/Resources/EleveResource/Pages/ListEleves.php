<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\EleveResource\Pages;

use App\Modules\Scolarite\Filament\Resources\EleveResource;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use App\Modules\Scolarite\Models\Eleve;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListEleves extends ListRecords
{
    protected static string $resource = EleveResource::class;

    protected static ?string $title = 'Admissions';

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
                ->label(fn (): string => $this->usesCardLayout()
                    ? 'Vue tableau'
                    : 'Vue cartes'
                )
                ->icon(fn (): string => $this->usesCardLayout()
                    ? 'heroicon-o-table-cells'
                    : 'heroicon-o-squares-2x2'
                )
                ->color('gray')
                ->action(fn (): mixed => $this->toggleLayout()),

            ScolariteActions::create()
                ->label('Nouvelle admission'),
        ];
    }

    protected function getEmptyStateActions(): array
    {
        return [
            ScolariteActions::create()
                ->label('Nouvelle admission'),
        ];
    }
}
