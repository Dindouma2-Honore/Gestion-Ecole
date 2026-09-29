<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages;

use App\Filament\Concerns\HasCardListLayout;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;

class ListInscriptions extends ListRecords
{
    use HasCardListLayout;

    protected static string $resource = InscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->cardLayoutAction(),

            Actions\CreateAction::make()
                ->label('Nouvelle inscription'),
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