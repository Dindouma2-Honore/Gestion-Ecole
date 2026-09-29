<?php

declare(strict_types=1);

namespace App\Modules\RH\Traits;

use App\Models\User;
use App\Modules\RH\Exceptions\TransitionStatutCongeInvalideException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait HasWorkflowStatus
{
    /**
     * @throws TransitionStatutCongeInvalideException
     */
    public function changerStatut(string $nouveauStatut, ?User $acteur = null, ?string $commentaire = null): void
    {
        $statutActuel = (string) ($this->statut ?? '');
        $transitionsValides = $this->transitionsAutorisees()[$statutActuel] ?? [];

        if (! in_array($nouveauStatut, $transitionsValides, true)) {
            throw new TransitionStatutCongeInvalideException($statutActuel, $nouveauStatut);
        }

        DB::transaction(function () use ($nouveauStatut, $acteur, $commentaire): void {
            $this->historiqueStatuts()->create([
                'statut' => $nouveauStatut,
                'changed_by' => $acteur?->id ?? Auth::id(),
                'commentaire' => $commentaire,
                'changed_at' => now(),
            ]);

            $this->update(['statut' => $nouveauStatut]);
        });
    }

    public function estAuStatut(string $statut): bool
    {
        return $this->statut === $statut;
    }
}
