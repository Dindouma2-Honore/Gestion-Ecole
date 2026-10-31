<?php

declare(strict_types=1);

namespace App\Modules\Socle\Data;

readonly class SchoolProfileDTO
{
    /** @param list<int> $workingDays */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $acronym,
        public ?string $logoPath,
        public ?string $address,
        public ?string $phone,
        public ?string $email,
        public ?string $website,
        public ?string $rccm,
        public ?string $niu,
        public string $currency,
        public ?string $openingTime,
        public ?string $closingTime,
        public array $workingDays,
        public int $lateToleranceMinutes,
    ) {}
}
