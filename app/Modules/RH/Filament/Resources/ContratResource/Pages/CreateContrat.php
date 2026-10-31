<?php

namespace App\Modules\RH\Filament\Resources\ContratResource\Pages;

use App\Modules\RH\Contracts\ContratServiceInterface;
use App\Modules\RH\Filament\Resources\ContratResource;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CreateContrat extends CreateRecord
{
    protected static string $resource = ContratResource::class;

    protected static ?string $title = 'Nouveau contrat / renouvellement';

    protected function handleRecordCreation(array $data): Model
    {
        $data['date_debut'] = $data['date_debut'] ?? today()->toDateString();
        if (($data['type'] ?? null) !== 'CDI' && empty($data['date_fin'])) {
            try {
                $data['date_fin'] = app(AnneeScolaireServiceContract::class)->getAnneeCourante()->date_fin->toDateString();
            } catch (\Throwable) {
                // Pas d'année scolaire active trouvée
            }
        }
        if (($data['categorie_paie'] ?? null) === 'horaire' && empty($data['salaire_base'])) {
            $data['salaire_base'] = 0;
        }

        // En cas de renouvellement d'un personnel ayant déjà un contrat actif :
        $contratActif = Contrat::where('employe_id', $data['employe_id'])
            ->where('statut', 'actif')
            ->first();

        if ($contratActif) {
            $dateDebut = Carbon::parse($data['date_debut'] ?? today());
            $updateData = ['statut' => 'expire'];
            if ($contratActif->date_fin === null || $contratActif->date_fin->gte($dateDebut)) {
                $updateData['date_fin'] = $dateDebut->copy()->subDay();
            }
            $contratActif->update($updateData);
        }

        try {
            $nouveauContrat = app(ContratServiceInterface::class)->creerContrat($data);
            Employe::where('id', $data['employe_id'])->update(['contrat_id' => $nouveauContrat->id]);

            return $nouveauContrat;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erreur lors de la création du contrat')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
