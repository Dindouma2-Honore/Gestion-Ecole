<?php

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\EnrollmentNotificationRecipient;
use App\Modules\Scolarite\Models\Eleve;

class EnrollmentNotificationRecipientService implements EnrollmentNotificationRecipient
{
    public function telephone(int $eleveId): ?string
    {
        return Eleve::find($eleveId)?->parentsTuteurs()->wherePivot('responsable_legal', true)->orderBy('parents_tuteurs.id')->first()?->telephone;
    }
}
