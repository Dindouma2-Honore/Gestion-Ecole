<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\TransfertAffectationResource\Pages;

use App\Modules\Scolarite\Filament\Resources\TransfertAffectationResource;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\TransfertAffectation;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTransfertAffectation extends CreateRecord
{
    protected static string $resource = TransfertAffectationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): TransfertAffectation {
            $inscription = Inscription::query()->lockForUpdate()->findOrFail($data['inscription_id']);
            $destination = Classe::query()->findOrFail($data['classe_destination_id']);

            if ((int) $destination->annee_scolaire_id !== (int) $inscription->annee_scolaire_id) {
                throw ValidationException::withMessages(['classe_destination_id' => 'La nouvelle classe doit appartenir à la même année scolaire.']);
            }
            if ((int) $destination->id === (int) $inscription->classe_id) {
                throw ValidationException::withMessages(['classe_destination_id' => 'Choisissez une classe différente de la classe actuelle.']);
            }

            $effectif = Inscription::query()
                ->where('classe_id', $destination->id)
                ->whereNotIn('statut', ['annulee'])
                ->count();
            if ($effectif >= (int) $destination->capacite_max) {
                throw ValidationException::withMessages(['classe_destination_id' => 'Cette classe a atteint sa capacité maximale.']);
            }

            $transfert = TransfertAffectation::query()->create([
                'inscription_id' => $inscription->id,
                'eleve_id' => $inscription->eleve_id,
                'classe_origine_id' => $inscription->classe_id,
                'classe_destination_id' => $destination->id,
                'annee_scolaire_id' => $inscription->annee_scolaire_id,
                'date_transfert' => $data['date_transfert'],
                'motif' => $data['motif'],
                'effectue_par' => auth()->id(),
            ]);

            $inscription->update(['classe_id' => $destination->id, 'type' => 'transfert']);

            app(AuditServiceContract::class)->enregistrerAvecMotif(
                $transfert,
                "Transfert de l'élève #{$inscription->eleve_id} vers la classe #{$destination->id}",
                $data['motif'],
            );

            return $transfert;
        });
    }
}
