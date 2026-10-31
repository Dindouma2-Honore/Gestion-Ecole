<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\ClasseResource\Pages;

use App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract;
use App\Modules\Scolarite\Filament\Resources\ClasseResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class ViewClasse extends ViewRecord
{
    protected static string $resource = ClasseResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Modifier la classe')];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            View::make('scolarite::filament.pages.classe-details')
                ->viewData(fn (): array => $this->details())
                ->columnSpanFull(),
        ]);
    }

    /** @return array<string, mixed> */
    private function details(): array
    {
        return [
            'classe' => $this->record,
            'configuration' => $this->configuration(),
            'resume' => ClasseResource::resume($this->record),
            'niveau' => $this->libelle('niveaux', 'nom', $this->record->niveau_id),
            'annee' => $this->libelle('annees_scolaires', 'libelle', $this->record->annee_scolaire_id),
            'salle' => $this->libelle('salles', 'nom', $this->record->salle_principale_id),
            'section' => $this->libelle('sections_scolaires', 'nom', $this->record->section_id),
            'filiere' => $this->libelle('filieres', 'nom', $this->record->filiere_id),
            'enseignant' => $this->enseignantPrincipal(),
        ];
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return app(ConfigurationFraisClasseServiceContract::class)
            ->obtenir($this->record->id, (int) $this->record->annee_scolaire_id) ?? [];
    }

    private function libelle(string $table, string $colonne, mixed $id): string
    {
        return $id ? (string) (DB::table($table)->where('id', $id)->value($colonne) ?? 'Non défini') : 'Non défini';
    }

    private function enseignantPrincipal(): string
    {
        if (! $this->record->professeur_principal_id) {
            return 'Non attribué';
        }

        $employe = DB::table('enseignants')->join('employes', 'employes.id', '=', 'enseignants.employe_id')
            ->where('enseignants.id', $this->record->professeur_principal_id)
            ->select('employes.nom', 'employes.prenom')->first();

        return $employe ? trim($employe->nom.' '.$employe->prenom) : 'Non attribué';
    }
}
