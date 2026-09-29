<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

use DateTimeInterface;

interface BilanJournalierScolariteContract
{
    public function getBilan(DateTimeInterface $date): object;
}
