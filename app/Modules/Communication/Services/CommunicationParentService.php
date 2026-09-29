<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Models\User;
use App\Modules\Communication\Contracts\CommunicationParentServiceContract;
use App\Modules\Communication\Contracts\NotificationServiceContract;
use App\Modules\Communication\Models\MessageParent;
use App\Modules\Communication\Models\ReponseParent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommunicationParentService implements CommunicationParentServiceContract
{
    public function __construct(
        private NotificationServiceContract $notification
    ) {}

    public function envoyerMessageIndividuel(int $parentId, string $sujet, string $contenu): object
    {
        return DB::transaction(function () use ($parentId, $sujet, $contenu) {
            $message = MessageParent::create([
                'type' => 'individuel',
                'expediteur_id' => Auth::id() ?? 1,
                'sujet' => $sujet,
                'contenu' => $contenu,
            ]);

            $this->creerDestinataireEtNotifier($message, $parentId);

            return $message;
        });
    }

    public function envoyerMessageCollectif(string $cibleType, int $cibleId, string $sujet, string $contenu): object
    {
        return DB::transaction(function () use ($cibleType, $cibleId, $sujet, $contenu) {
            $message = MessageParent::create([
                'type' => 'collectif',
                'expediteur_id' => Auth::id() ?? 1,
                'sujet' => $sujet,
                'contenu' => $contenu,
                'cible_type' => $cibleType,
                'cible_id' => $cibleId,
            ]);

            $parentIds = $this->resoudreParentsDeLaCible($cibleType, $cibleId);

            foreach ($parentIds as $parentId) {
                $this->creerDestinataireEtNotifier($message, (int) $parentId);
            }

            return $message;
        });
    }

    private function resoudreParentsDeLaCible(string $cibleType, int $cibleId): Collection
    {
        return match ($cibleType) {
            'classe' => DB::table('inscriptions')
                ->join('eleve_parent', 'inscriptions.eleve_id', '=', 'eleve_parent.eleve_id')
                ->where('inscriptions.classe_id', $cibleId)
                ->where('inscriptions.statut', 'validee')
                ->pluck('eleve_parent.parent_id')
                ->unique(),
            'niveau' => DB::table('eleves')
                ->join('inscriptions', 'eleves.id', '=', 'inscriptions.eleve_id')
                ->join('eleve_parent', 'eleves.id', '=', 'eleve_parent.eleve_id')
                ->where('inscriptions.niveau_id', $cibleId)
                ->pluck('eleve_parent.parent_id')
                ->unique(),
            'tous' => DB::table('parents_tuteurs')->pluck('id'),
            default => collect(),
        };
    }

    private function creerDestinataireEtNotifier(MessageParent $message, int $parentId): void
    {
        $destinataire = $message->destinataires()->create([
            'parent_id' => $parentId,
        ]);

        $parentData = DB::table('parents_tuteurs')->where('id', $parentId)->first();
        $parentObj = $parentData ? (object) [
            'id' => $parentData->id,
            'telephone' => $parentData->telephone ?? '',
            'email' => $parentData->email ?? '',
            'getKey' => fn () => $parentData->id,
        ] : null;

        $notification = $this->notification->envoyer(
            canal: 'whatsapp',
            code: 'nouveau_message_etablissement',
            destinataire: $parentObj,
            donnees: ['sujet' => $message->sujet]
        );

        if (isset($notification->id)) {
            $destinataire->update(['notification_id' => $notification->id]);
        }
    }

    public function repondre(int $messageId, int $parentId, string $contenu): object
    {
        $reponse = ReponseParent::create([
            'message_id' => $messageId,
            'parent_id' => $parentId,
            'contenu' => $contenu,
        ]);

        $message = MessageParent::find($messageId);
        if ($message) {
            $expediteur = User::find($message->expediteur_id);
            $this->notification->envoyer(
                canal: 'in_app',
                code: 'reponse_parent_recue',
                destinataire: $expediteur,
                donnees: ['parent' => $parentId]
            );
        }

        return $reponse;
    }

    public function notifierEvenement(int $parentId, string $typeEvenement, array $donnees): void
    {
        $parentData = DB::table('parents_tuteurs')->where('id', $parentId)->first();
        $parentObj = $parentData ? (object) [
            'id' => $parentData->id,
            'telephone' => $parentData->telephone ?? '',
            'email' => $parentData->email ?? '',
            'getKey' => fn () => $parentData->id,
        ] : null;

        $this->notification->envoyer('whatsapp', $typeEvenement, null, $donnees);
    }
}
