<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts\Observers;

use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\RattacheAAnneeScolaire;
use App\Modules\Socle\Exceptions\AnneeVerrouilleeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

class VerrouAnneeObserver
{
    public function saving(Model $model): void
    {
        $this->verifier($model);
    }

    public function deleting(Model $model): void
    {
        $this->verifier($model);
    }

    private function verifier(Model $model): void
    {
        if (! $model instanceof RattacheAAnneeScolaire) {
            return;
        }

        // Résolu via le Contract (pas le Model AnneeScolaire directement) :
        // cette classe vit désormais dans Socle\Contracts, qui ne peut
        // dépendre que d'autres Contracts (voir deptrac.yaml). getAnneeScolaire()
        // renvoie un `object` générique — jamais le type concret AnneeScolaire.
        // findOrFail() sous-jacent peut lever si l'année est introuvable : on
        // traite ce cas comme "pas de restriction", au même titre que
        // l'ancien `AnneeScolaire::find()` nullable.
        try {
            $annee = app(AnneeScolaireServiceContract::class)->getAnneeScolaire($model->getAnneeScolaireId());
        } catch (Throwable) {
            return;
        }

        if (! in_array($annee->statut, ['cloturee', 'archivee'], true)) {
            return;
        }

        // Exception : le Fondateur peut corriger une année clôturée (jamais archivée)
        $user = Auth::user();
        if ($annee->statut === 'cloturee' && $user && method_exists($user, 'hasRole') && $user->hasRole('Fondateur')) {
            return;
        }

        throw new AnneeVerrouilleeException($annee->libelle, $annee->statut);
    }
}
