<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

interface WorkflowServiceContract
{
    public function demarrerWorkflow(string $code, object $entite, string $moduleSource, array $contexte = []): object;

    public function transitionner(int $instanceId, string $decision, int $acteurId, string $motif): object;

    public function peutEtreAutoValide(string $code, array $contexte): bool;
}
