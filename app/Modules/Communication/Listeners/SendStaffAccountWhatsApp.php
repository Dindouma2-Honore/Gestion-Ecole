<?php

declare(strict_types=1);

namespace App\Modules\Communication\Listeners;

use App\Models\User;
use App\Modules\Communication\Services\StaffAccountWhatsApp;
use App\Modules\Socle\Contracts\Events\UserAccountCreated;
use App\Modules\Socle\Contracts\Events\UserAccountReactivated;

final class SendStaffAccountWhatsApp
{
    public function handle(UserAccountCreated|UserAccountReactivated $event): void
    {
        $user = User::query()->find($event->userId);
        if ($user) {
            app(StaffAccountWhatsApp::class)->dispatch($user);
        }
    }
}
