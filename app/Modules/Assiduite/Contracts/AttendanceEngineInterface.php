<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Contracts;

use App\Modules\Assiduite\Models\AssiduiteJournaliere;
use DateTimeInterface;

interface AttendanceEngineInterface
{
    public function calculer(string $personneType, int $personneId, DateTimeInterface $date): AssiduiteJournaliere;

    public function recalculer(string $personneType, int $personneId, DateTimeInterface $date): AssiduiteJournaliere;
}
