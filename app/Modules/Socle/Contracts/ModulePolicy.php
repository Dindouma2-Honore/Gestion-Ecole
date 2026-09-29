<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

/** Contrat commun aux Policies déclarant l'accès à un domaine fonctionnel. */
interface ModulePolicy
{
    /** @return list<string> */
    public static function rolesAutorises(): array;
}
