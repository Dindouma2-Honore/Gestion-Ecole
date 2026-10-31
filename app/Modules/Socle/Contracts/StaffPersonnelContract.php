<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Modules\Socle\Data\StaffPersonnelData;

interface StaffPersonnelContract
{
    public function activeForAccount(int $personnelId): ?StaffPersonnelData;

    public function linkUser(int $personnelId, int $userId): void;
}
