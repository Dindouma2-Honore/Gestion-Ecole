<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\ReunionServiceContract;
use App\Modules\Socle\Contracts\TacheServiceContract;
use App\Modules\Socle\Exceptions\ReunionDejaClotureeException;
use App\Modules\Socle\Models\Decision;
use App\Modules\Socle\Models\Reunion;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReunionService implements ReunionServiceContract
{
    public function __construct(
        private TacheServiceContract $tacheService
    ) {}

    public function planifierReunion(array $donnees, array $participantIds, array $ordreDuJour): object
    {
        $createurId = Auth::id();

        if ($createurId === null) {
            throw new AccessDeniedHttpException('Une authentification est requise pour planifier une réunion.');
        }

        return DB::transaction(function () use ($donnees, $participantIds, $ordreDuJour, $createurId) {
            $reunion = Reunion::create(array_merge($donnees, [
                'statut' => 'planifiee',
                'created_by' => $createurId,
            ]));

            foreach ($participantIds as $userId) {
                $reunion->participants()->create(['user_id' => $userId]);
                
                $participantUser = \App\Models\User::find($userId);
                if ($participantUser && $participantUser->email) {
                    try {
                        \Illuminate\Support\Facades\Mail::to($participantUser->email)
                            ->send(new \App\Mail\ReunionOrganiseeMail($reunion, $participantUser));
                    } catch (\Throwable $e) {
                        logger()->error("Impossible d'envoyer l'invitation réunion à {$participantUser->email}: ".$e->getMessage());
                    }
                }
            }

            foreach ($ordreDuJour as $index => $point) {
                $reunion->ordreDuJour()->create(['point' => $point, 'ordre' => $index + 1]);
            }

            return $reunion;
        });
    }

    public function marquerPresence(int $reunionId, int $userId, bool $present): void
    {
        Reunion::findOrFail($reunionId)
            ->participants()
            ->where('user_id', $userId)
            ->update(['present' => $present]);
    }

    public function ajouterDecision(int $reunionId, string $description, int $responsableId, DateTimeInterface $echeance): object
    {
        return DB::transaction(function () use ($reunionId, $description, $responsableId, $echeance) {
            $reunion = Reunion::findOrFail($reunionId);

            if ($reunion->statut === 'terminee') {
                throw new ReunionDejaClotureeException($reunionId);
            }

            // 1. Créer la Tâche via le contrat du module A7 (pas d'accès direct)
            $tache = $this->tacheService->creerTache(
                titre: "Décision réunion #{$reunion->id} : ".Str::limit($description, 60),
                responsableId: $responsableId,
                echeance: $echeance,
                taskable: $reunion,
                description: $description
            );

            // 2. Créer la décision, en la liant à la tâche
            return Decision::create([
                'reunion_id' => $reunionId,
                'description' => $description,
                'responsable_id' => $responsableId,
                'echeance' => $echeance,
                'tache_id' => $tache->id,
            ]);
        });
    }

    public function cloturerReunion(int $reunionId, string $compteRendu): void
    {
        Reunion::findOrFail($reunionId)->update([
            'statut' => 'terminee',
            'compte_rendu' => $compteRendu,
        ]);
    }
}
