<?php

namespace App\Modules\RH\Filament\Resources\EmployeResource\Pages;

use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\RH\Models\Candidature;
use App\Modules\RH\Models\PosteAdministratif;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

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
        $posteAdminId = PosteAdministratif::query()->where('nom', $candidature->poste_souhaite)->value('id');
        $this->form->fill(array_merge($this->data, [
            'candidature_id' => $candidature->id,
            'nom' => $candidature->nom,
            'prenom' => $candidature->prenom,
            'email' => $candidature->email,
            'telephone' => $candidature->telephone,
            'poste_administratif_id' => $posteAdminId,
            'poste' => $candidature->poste_souhaite,
            'role_id' => Role::query()->where('name', $candidature->poste_souhaite)->value('id'),
            'date_debut_contrat' => now()->toDateString(),
            'statut' => 'actif',
        ]));
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $resultat = app(EmployeServiceContract::class)->embaucher($data);
            $this->motDePasseTemporaire = $resultat['mot_de_passe_temporaire'];

            return $resultat['employe'];
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erreur lors de l’intégration')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Intégration finalisée')
            ->body("Le membre du personnel, son compte et son contrat ont été créés. Mot de passe temporaire : {$this->motDePasseTemporaire}")
            ->persistent();
    }
}
