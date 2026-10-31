<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Modules\Socle\Data\AuthorizationDecisionData;
use App\Modules\Socle\Data\AuthorizationGrantData;
use App\Modules\Socle\Data\AuthorizationSubjectData;
use App\Modules\Socle\Enums\AuthorizationAction;

interface AuthorizationContract
{
    public function decide(int $userId, string $featureCode, AuthorizationAction $action): AuthorizationDecisionData;

    public function canRead(int $userId, string $featureCode): bool;

    public function canWrite(int $userId, string $featureCode): bool;

    public function canExecute(int $userId, string $featureCode): bool;

    public function canPrint(int $userId, string $featureCode): bool;

    public function grant(AuthorizationSubjectData $subject, string $featureCode, AuthorizationAction ...$actions): AuthorizationGrantData;

    /** @return list<AuthorizationGrantData> */
    public function grantsFor(AuthorizationSubjectData $subject): array;
}
