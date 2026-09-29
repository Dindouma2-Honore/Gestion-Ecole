<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Communication\Contracts\NotificationServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\WorkflowServiceContract;
use App\Modules\Socle\Models\WorkflowDefinition;
use App\Modules\Socle\Models\WorkflowEtape;
use App\Modules\Socle\Models\WorkflowInstance;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WorkflowService implements WorkflowServiceContract
{
    public function __construct(
        private readonly AuditServiceContract $audit,
        private readonly ?NotificationServiceContract $notifications = null,
    ) {}

    public function demarrerWorkflow(string $code, object $entite, string $moduleSource, array $contexte = []): object
    {
        $definition = $this->definitionActive($code);
        $etapes = $definition->etapes->filter(fn (WorkflowEtape $etape): bool => $this->conditionRemplie($etape->condition, $contexte))->values();

        if ($etapes->isEmpty()) {
            throw new \DomainException("Aucune étape applicable au workflow {$code}.");
        }

        $instance = WorkflowInstance::query()->create([
            'workflow_definition_id' => $definition->id,
            'workflow_definition_version' => $definition->version,
            'module_source' => $moduleSource,
            'entite_type' => $entite::class,
            'entite_id' => $entite->getKey(),
            'statut' => 'en_cours',
            'etape_courante_id' => $etapes->first()->id,
            'definition_snapshot' => $etapes->map->only(['id', 'ordre', 'nom', 'validateur_type', 'validateur_valeur', 'condition'])->all(),
            'contexte' => $contexte,
        ]);
        $this->notifierValidateurs($etapes->first(), $instance);

        return $instance;
    }

    public function transitionner(int $instanceId, string $decision, int $acteurId, string $motif): object
    {
        if (! in_array($decision, ['valide', 'rejete'], true) || trim($motif) === '') {
            throw new \DomainException('Une décision valide et un motif sont obligatoires.');
        }

        return DB::transaction(function () use ($instanceId, $decision, $acteurId, $motif): WorkflowInstance {
            $instance = WorkflowInstance::query()->lockForUpdate()->findOrFail($instanceId);
            if ($instance->statut !== 'en_cours' || ! $instance->etape_courante_id) {
                throw new \DomainException('Ce workflow ne peut plus être transitionné.');
            }
            $etape = WorkflowEtape::query()->findOrFail($instance->etape_courante_id);
            $acteur = User::query()->findOrFail($acteurId);
            if (! $this->estValidateur($acteur, $etape)) {
                throw new AccessDeniedHttpException('Cet utilisateur ne peut pas valider cette étape.');
            }

            $instance->transitions()->create(['etape_id' => $etape->id, 'acteur_id' => $acteurId, 'decision' => $decision, 'motif' => trim($motif)]);
            if ($decision === 'rejete') {
                $instance->update(['statut' => 'rejete', 'etape_courante_id' => null]);
            } else {
                $snapshot = collect($instance->definition_snapshot);
                $prochaine = $snapshot->first(fn (array $item): bool => (int) $item['ordre'] > $etape->ordre);
                $instance->update($prochaine
                    ? ['etape_courante_id' => $prochaine['id']]
                    : ['statut' => 'valide', 'etape_courante_id' => null]);
                if ($prochaine) {
                    $this->notifierValidateurs(WorkflowEtape::query()->findOrFail($prochaine['id']), $instance);
                }
            }
            $this->audit->enregistrerAvecMotif($instance, "Workflow : {$decision}", trim($motif));

            return $instance->refresh();
        });
    }

    public function peutEtreAutoValide(string $code, array $contexte): bool
    {
        return $this->definitionActive($code)->etapes
            ->filter(fn (WorkflowEtape $etape): bool => $this->conditionRemplie($etape->condition, $contexte))
            ->isEmpty();
    }

    private function definitionActive(string $code): WorkflowDefinition
    {
        return WorkflowDefinition::query()->with('etapes')->where('code', $code)->where('actif', true)->latest('version')->firstOrFail();
    }

    private function conditionRemplie(?array $condition, array $contexte): bool
    {
        if (! $condition) {
            return true;
        }
        $montant = (float) ($contexte['montant'] ?? 0);
        $categorie = (string) ($contexte['categorie_id'] ?? '');
        $seuil = (float) ($condition['seuils_par_categorie'][$categorie] ?? $condition['seuil_defaut'] ?? 0);

        return $montant >= $seuil;
    }

    private function estValidateur(User $acteur, WorkflowEtape $etape): bool
    {
        return match ($etape->validateur_type) {
            'fondateur' => $acteur->hasRole('Fondateur'),
            'role' => $acteur->hasRole((string) $etape->validateur_valeur),
            'utilisateur' => $acteur->getKey() === (int) $etape->validateur_valeur,
            default => false,
        };
    }

    private function notifierValidateurs(WorkflowEtape $etape, WorkflowInstance $instance): void
    {
        if (! $this->notifications) {
            return;
        }
        $users = match ($etape->validateur_type) {
            'fondateur' => User::role('Fondateur')->get(),
            'role' => User::role((string) $etape->validateur_valeur)->get(),
            'utilisateur' => User::query()->whereKey((int) $etape->validateur_valeur)->get(),
            default => collect(),
        };
        foreach ($users as $user) {
            $this->notifications->envoyer('in_app', 'workflow_validation_requise', $user, ['workflow_instance_id' => $instance->id, 'etape' => $etape->nom]);
        }
    }
}
