<?php

declare(strict_types=1);

namespace App\Modules\Socle\Traits;

use App\Models\User;
use App\Modules\Socle\Exceptions\TransitionStatutInvalideException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait HasWorkflowStatus
{
    /**
     * @throws TransitionStatutInvalideException
     */
    public function changerStatut(string $nouveauStatut, ?User $acteur = null, ?string $commentaire = null): void
    {
        $statutActuel = (string) ($this->statut ?? '');
        $transitionsValides = $this->transitionsAutorisees()[$statutActuel] ?? [];

        if (! in_array($nouveauStatut, $transitionsValides, true)) {
            throw new TransitionStatutInvalideException($statutActuel, $nouveauStatut);
        }

        DB::transaction(function () use ($nouveauStatut, $acteur, $commentaire) {
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
