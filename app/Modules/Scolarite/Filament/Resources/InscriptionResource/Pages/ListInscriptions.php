<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages;

use App\Filament\Concerns\HasCardListLayout;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use App\Modules\Scolarite\Models\Inscription;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListInscriptions extends ListRecords
{
    use HasCardListLayout;

    protected static string $resource = InscriptionResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'inscrits';
    }

    public function getTabs(): array
    {
        $anneeActiveId = \App\Modules\Socle\Models\AnneeScolaire::where('statut', 'active')
            ->value('id');

        return [
            'inscrits' => Tab::make('Élèves inscrits')
                ->icon('heroicon-o-academic-cap')
                ->badge(fn () => Inscription::query()
                    ->withoutGlobalScopes()
                    ->whereNotIn('statut', ['annulee'])
                    ->when($anneeActiveId, fn ($q) => $q->where('annee_scolaire_id', $anneeActiveId))
                    ->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotIn('statut', ['annulee'])
                    ->when($anneeActiveId, fn ($q) => $q->where('annee_scolaire_id', $anneeActiveId))),

            'en_attente_annulation' => Tab::make("En attente d'annulation")
                ->icon('heroicon-o-clock')
                ->badge(fn () => Inscription::query()->withoutGlobalScopes()->where('statut', 'en_attente_annulation')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('statut', 'en_attente_annulation')),

            'annulees' => Tab::make('Annulées')
                ->icon('heroicon-o-x-circle')
                ->badge(fn () => Inscription::query()->withoutGlobalScopes()->where('statut', 'annulee')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('statut', 'annulee')),

            'toutes' => Tab::make('Toutes les inscriptions')
                ->icon('heroicon-o-squares-2x2')
                ->badge(fn () => Inscription::query()->withoutGlobalScopes()->count()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->cardLayoutAction(),

            InscriptionResource::nouvelleInscriptionModalAction(),
        ];
    }

    protected function getEmptyStateActions(): array
    {
        return [
            InscriptionResource::nouvelleInscriptionModalAction(),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('imprimer')
                ->label('Imprimer les inscriptions')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->modalHeading('Options d’impression')
                ->modalDescription(
                    'Choisissez les informations à afficher sur la liste imprimée.'
                )
                ->modalSubmitActionLabel('Préparer l’impression')
                ->form([
                    CheckboxList::make('colonnes')
                        ->label('Informations à afficher')
                        ->options([
                            'matricule' => 'Matricule',
                            'eleve' => 'Élève',
                            'sexe' => 'Sexe',
                            'classe' => 'Classe',
                            'annee' => 'Année scolaire',
                            'type' => 'Type d’inscription',
                            'date' => 'Date d’inscription',
                            'statut' => 'Statut de l’inscription',
                        ])
                        ->default([
                            'matricule',
                            'eleve',
                            'classe',
                            'annee',
                            'statut',
                        ])
                        ->columns(2)
                        ->required(),
                ])
                ->action(function (Collection $records, array $data): void {
                    if ($records->isEmpty()) {
                        return;
                    }

                    $ids = $records
                        ->pluck('id')
                        ->filter()
                        ->map(fn ($id): int => (int) $id)
                        ->unique()
                        ->values()
                        ->implode(',');

                    $colonnes = collect($data['colonnes'] ?? [])
                        ->filter()
                        ->values()
                        ->implode(',');

                 $url = route('impressions.inscriptions', [
                    'ids' => $ids,
                    'colonnes' => $colonnes,
                ]);

                    $this->js(
                        'window.open(' . json_encode($url) . ', "_blank");'
                    );
                }),
        ];
    }
}