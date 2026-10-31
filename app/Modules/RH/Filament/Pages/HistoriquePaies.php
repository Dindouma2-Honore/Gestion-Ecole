<?php

namespace App\Modules\RH\Filament\Pages;

use App\Modules\RH\Models\AvanceSalaire;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Models\Employe;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HistoriquePaies extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources Humaines';

    protected static ?string $title = 'Historique des paies';

    protected static ?string $slug = 'historique-paies';

    protected string $view = 'rh::filament.pages.historique-paies';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'dateDebut' => now()->startOfYear()->toDateString(),
            'dateFin' => now()->endOfYear()->toDateString(),
            'type' => 'tous',
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Fondateur', 'Comptable', 'Responsable RH']) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Select::make('employeId')->label('Personnel')
                ->options(fn (): array => Employe::query()->orderBy('nom')->orderBy('prenom')->get()
                    ->mapWithKeys(fn (Employe $employe): array => [$employe->id => $employe->nom_complet])->all())
                ->searchable()->live(),
            DatePicker::make('dateDebut')->label('Du')->live(),
            DatePicker::make('dateFin')->label('Au')->live(),
            Select::make('type')->label('Type')
                ->options(['tous' => 'Tous', 'paie' => 'Salaires', 'avance' => 'Avances'])
                ->default('tous')->live(),
        ]);
    }

    public function getBulletins(): Collection
    {
        if (($this->data['type'] ?? 'tous') === 'avance') {
            return collect();
        }

        return BulletinPaie::query()
            ->with('employe')
            ->whereIn('statut', ['valide', 'paye'])
            ->when($this->data['employeId'] ?? null, fn (Builder $query, $id): Builder => $query->where('employe_id', $id))
            ->when($this->data['dateDebut'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('periode_fin', '>=', $date))
            ->when($this->data['dateFin'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('periode_debut', '<=', $date))
            ->orderByDesc('periode_fin')
            ->orderByDesc('id')
            ->get();
    }

    public function getAvances(): Collection
    {
        if (($this->data['type'] ?? 'tous') === 'paie') {
            return collect();
        }

        return AvanceSalaire::query()
            ->with('employe')
            ->whereIn('statut', ['approuvee', 'remboursee'])
            ->when($this->data['employeId'] ?? null, fn (Builder $query, $id): Builder => $query->where('employe_id', $id))
            ->when($this->data['dateDebut'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('date_demande', '>=', $date))
            ->when($this->data['dateFin'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('date_demande', '<=', $date))
            ->orderByDesc('date_demande')
            ->orderByDesc('id')
            ->get();
    }

    public function getTotalPaies(): float
    {
        return (float) $this->getBulletins()->sum('net_a_payer');
    }

    public function getTotalAvances(): float
    {
        return (float) $this->getAvances()->sum('montant');
    }
}
