<?php

namespace App\Modules\RH\Filament\Resources\EmployeResource\Pages;

use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\RH\Models\Candidature;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmploye extends CreateRecord
{
    protected static string $resource = EmployeResource::class;

    protected ?string $motDePasseTemporaire = null;

    public function mount(): void
    {
        parent::mount();

        $candidatureId = request()->integer('candidature');
        if (! $candidatureId) {
            return;
        }

        $candidature = Candidature::query()->where('statut', 'retenue')->findOrFail($candidatureId);
        $this->form->fill(array_merge($this->data, [
            'nom' => $candidature->nom,
            'prenom' => $candidature->prenom,
            'email' => $candidature->email,
            'telephone' => $candidature->telephone,
            'poste' => $candidature->poste_souhaite,
            'date_embauche' => now()->toDateString(),
            'date_debut_contrat' => now()->toDateString(),
            'statut' => 'actif',
        ]));
    }

    protected function handleRecordCreation(array $data): Model
    {
        $resultat = app(EmployeServiceContract::class)->embaucher($data);
        $this->motDePasseTemporaire = $resultat['mot_de_passe_temporaire'];

        if ($candidatureId = request()->integer('candidature')) {
            Candidature::query()->whereKey($candidatureId)->where('statut', 'retenue')->update([
                'statut' => 'embauche',
                'employe_id' => $resultat['employe']->id,
            ]);
        }

        return $resultat['employe'];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Embauche finalisée')
            ->body("Le dossier, le compte et le contrat ont été créés. Mot de passe temporaire : {$this->motDePasseTemporaire}")
            ->persistent();
    }
}
