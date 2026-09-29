<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Contracts\PortailEleveServiceContract;
use App\Modules\Communication\Contracts\PortailParentServiceContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PortailEleveService implements PortailEleveServiceContract
{
    public function __construct(
        private PortailParentServiceContract $portailParent
    ) {}

    public function getVuePersonnelle(int $eleveUserId): object
    {
        $eleve = DB::table('eleves')->where('user_id', $eleveUserId)->first();

        if (! $eleve) {
            throw new ModelNotFoundException("Élève introuvable pour l'utilisateur #{$eleveUserId}");
        }

        $parentId = (int) (DB::table('eleve_parent')->where('eleve_id', $eleve->id)->value('parent_id') ?? 0);

        $vue = $this->portailParent->getVueEnfant($parentId, $eleve->id);

        unset($vue->reste_a_payer, $vue->historique_paiements);

        return $vue;
    }
}
