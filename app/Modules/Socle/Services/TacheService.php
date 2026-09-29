<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\TacheServiceContract;
use App\Modules\Socle\Exceptions\CircuitValidationIncompletException;
use App\Modules\Socle\Exceptions\ValidateurNonAutoriseException;
use App\Modules\Socle\Models\Tache;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TacheService implements TacheServiceContract
{
    public function creerTache(
        string $titre,
        int $responsableId,
        DateTimeInterface $echeance,
        ?Model $taskable = null,
        string $priorite = 'normale',
        ?string $description = null
    ): object {
        $createurId = Auth::id();

        if ($createurId === null) {
            throw new AccessDeniedHttpException('Une authentification est requise pour créer une tâche.');
        }

        $tache = Tache::create([
            'titre' => $titre,
            'description' => $description,
            'responsable_id' => $responsableId,
            'createur_id' => $createurId,
            'echeance' => $echeance,
            'priorite' => $priorite,
            'statut' => 'a_faire',
            'taskable_type' => $taskable?->getMorphClass(),
            'taskable_id' => $taskable?->getKey(),
        ]);

        $responsableUser = \App\Models\User::find($responsableId);
        if ($responsableUser && $responsableUser->email) {
            try {
                \Illuminate\Support\Facades\Mail::to($responsableUser->email)
                    ->send(new \App\Mail\TacheAssigneeMail($tache, $responsableUser));
            } catch (\Throwable $e) {
                logger()->error("Impossible d'envoyer l'email d'assignation tâche à {$responsableUser->email}: ".$e->getMessage());
            }
        }

        return $tache;
    }

    public function demarrerCircuitValidation(int $tacheId, array $validateurIds): void
    {
        if (empty($validateurIds)) {
            throw new CircuitValidationIncompletException;
        }

        DB::transaction(function () use ($tacheId, $validateurIds) {
            $tache = Tache::findOrFail($tacheId);

            foreach ($validateurIds as $index => $validateurId) {
                $tache->validations()->create([
                    'validateur_id' => $validateurId,
                    'niveau_validation' => $index + 1,
                    'statut' => 'en_attente',
                ]);
            }

            $tache->changerStatut('en_attente_validation');
        });
    }

    public function validerEtape(int $tacheId, int $validateurId, bool $approuve, ?string $commentaire = null): void
    {
        $tache = Tache::findOrFail($tacheId);
        $etape = $tache->etapeSuivanteAValider();

        if (! $etape || (int) $etape->validateur_id !== $validateurId) {
            throw new ValidateurNonAutoriseException($validateurId, $tacheId);
        }

        DB::transaction(function () use ($tache, $etape, $approuve, $commentaire) {
            $etape->update([
                'statut' => $approuve ? 'validee' : 'rejetee',
                'commentaire' => $commentaire,
                'validated_at' => now(),
            ]);

            if (! $approuve) {
                $tache->changerStatut('rejetee');

                return;
            }

            if (! $tache->etapeSuivanteAValider()) {
                $tache->changerStatut('validee');

                $responsableUser = \App\Models\User::find($tache->responsable_id);
                if ($responsableUser && $responsableUser->email) {
                    try {
                        \Illuminate\Support\Facades\Mail::to($responsableUser->email)
                            ->send(new \App\Mail\TacheValideeMail($tache, $responsableUser));
                    } catch (\Throwable $e) {
                        logger()->error("Impossible d'envoyer l'email de validation tâche à {$responsableUser->email}: ".$e->getMessage());
                    }
                }
            }
        });
    }

    public function getTachesEnRetard(): Collection
    {
        return Tache::where('echeance', '<', now())
            ->whereNotIn('statut', ['validee', 'cloturee'])
            ->get();
    }
}
