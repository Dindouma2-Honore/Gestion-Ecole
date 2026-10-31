<?php

namespace App\Modules\RH\Filament\Widgets;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Pages\HistoriquePaies;
use App\Modules\RH\Models\AvanceSalaire;
use App\Modules\RH\Models\BulletinPaie;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;

class HistoriquePaiesTable extends TableWidget
{
    #[Reactive]
    public array $filters = [];

    #[Locked]
    public string $type = 'paie';

    public static function canView(): bool
    {
        return HistoriquePaies::canAccess();
    }

    public function table(Table $table): Table
    {
        abort_unless(static::canView(), 403);
        $avance = $this->type === 'avance';
        $query = $avance ? AvanceSalaire::query()->whereIn('statut', ['approuvee', 'remboursee']) : BulletinPaie::query()->whereIn('statut', ['valide', 'paye']);
        $query->with('employe')
            ->when($this->filters['employeId'] ?? null, fn ($q, $id) => $q->where('employe_id', $id))
            ->when($this->filters['dateDebut'] ?? null, fn ($q, $date) => $q->whereDate($avance ? 'date_demande' : 'periode_fin', '>=', $date))
            ->when($this->filters['dateFin'] ?? null, fn ($q, $date) => $q->whereDate($avance ? 'date_demande' : 'periode_debut', '<=', $date));

        return AmbassadorsDesign::configureTable($table, null)
            ->heading($avance ? 'Historique des avances' : 'Historique des salaires')
            ->query($query)->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('employe.nom')->label('Personnel')->formatStateUsing(fn ($record) => $record->employe?->nom_complet)->searchable()->sortable(),
                TextColumn::make($avance ? 'date_demande' : 'periode_debut')->label($avance ? 'Date de demande' : 'Début')->date('d/m/Y')->sortable(),
                TextColumn::make('periode_fin')->label('Fin')->date('d/m/Y')->visible(! $avance),
                TextColumn::make('statut')->badge(),
                TextColumn::make($avance ? 'montant' : 'net_a_payer')->label('Montant')->money('XAF')->sortable(),
                TextColumn::make('montant_deja_deduit')->label('Déjà déduit')->money('XAF')->visible($avance),
            ]);
    }
}
