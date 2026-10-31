<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

interface AuthenticatedAuthorizationContract
{
    public function canRead(string $featureCode): bool;

    public function canWrite(string $featureCode): bool;

    public function canExecute(string $featureCode): bool;

    public function canPrint(string $featureCode): bool;
}
