<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\SchoolProfileContract;
use App\Modules\Socle\Data\SchoolProfileDTO;
use App\Modules\Socle\Models\ConfigEtablissement;

class SchoolProfileService implements SchoolProfileContract
{
    public function profile(): SchoolProfileDTO
    {
        $profile = ConfigEtablissement::get();

        return new SchoolProfileDTO(
            id: (int) $profile->id,
            name: (string) $profile->nom,
            acronym: $profile->sigle,
            logoPath: $profile->logo_path,
            address: $profile->adresse,
            phone: $profile->telephone,
            email: $profile->email,
            website: $profile->site_web,
            rccm: $profile->rccm,
            niu: $profile->niu,
            currency: (string) ($profile->devise ?: 'XAF'),
            openingTime: $profile->heure_ouverture,
            closingTime: $profile->heure_fermeture,
            workingDays: array_values(array_map('intval', $profile->jours_ouvrables ?? [])),
            lateToleranceMinutes: (int) ($profile->tolerance_retard_minutes ?? 0),
        );
    }
}
