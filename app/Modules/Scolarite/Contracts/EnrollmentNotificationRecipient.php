<?php

namespace App\Modules\Scolarite\Contracts;

interface EnrollmentNotificationRecipient
{
    public function telephone(int $eleveId): ?string;
}
