<?php

declare(strict_types=1);

namespace App\Modules\Communication\Listeners;

use App\Models\User;
use App\Modules\Communication\Contracts\NotificationServiceContract;
use App\Modules\Socle\Contracts\Events\UserFeatureAccessChanged;

final class SendFeatureAccessChangedNotification
{
    public function __construct(private readonly NotificationServiceContract $notifications) {}

    public function handle(UserFeatureAccessChanged $event): void
    {
        $user = User::find($event->userId);
        if (! $user || $user->statut !== 'actif') {
            return;
        }

        $data = [
            'fonctionnalite' => $event->featureName,
            'code' => $event->featureCode,
            'module' => $event->category,
            'action' => $event->granted ? 'accordee' : 'retiree',
            'date' => $event->occurredAt,
        ];
        if ($event->groupName !== null) {
            $data['groupe'] = $event->groupName;
        }

        $this->notifications->envoyer('in_app', 'habilitation_modifiee', $user, $data);
    }
}
