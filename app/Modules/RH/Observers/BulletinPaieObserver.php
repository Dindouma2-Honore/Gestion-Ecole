<?php

declare(strict_types=1);

namespace App\Modules\RH\Observers;

use App\Modules\RH\Exceptions\BulletinDejaValideException;
use App\Modules\RH\Models\BulletinPaie;

class BulletinPaieObserver
{
    /**
     * Handle the BulletinPaie "updating" event.
     *
     * @throws BulletinDejaValideException
     */
    public function updating(BulletinPaie $bulletin): void
    {
        $statutOriginal = $bulletin->getOriginal('statut');

        if (in_array($statutOriginal, ['valide', 'paye'], true)) {
            if ($statutOriginal === 'paye') {
                throw new BulletinDejaValideException((int) $bulletin->id);
            }

            // If status was 'valide', forbid altering financial or structural fields
            if ($bulletin->isDirty(['salaire_base', 'total_primes', 'total_heures_supplementaires', 'total_retenues', 'total_cotisations', 'avances_deduites', 'net_a_payer', 'employe_id', 'contrat_id', 'mois', 'annee'])) {
                throw new BulletinDejaValideException((int) $bulletin->id);
            }
        }
    }

    /**
     * Handle the BulletinPaie "deleting" event.
     *
     * @throws BulletinDejaValideException
     */
    public function deleting(BulletinPaie $bulletin): void
    {
        if (in_array($bulletin->statut, ['valide', 'paye'], true)) {
            throw new BulletinDejaValideException((int) $bulletin->id);
        }
    }

    /**
     * Handle the BulletinPaie "restoring" event.
     *
     * @throws BulletinDejaValideException
     */
    public function restoring(BulletinPaie $bulletin): void
    {
        if (in_array($bulletin->statut, ['valide', 'paye'], true)) {
            throw new BulletinDejaValideException((int) $bulletin->id);
        }
    }
}
