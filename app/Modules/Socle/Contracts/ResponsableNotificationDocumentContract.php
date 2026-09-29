<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Models\User;

/** Surface publique optionnelle pour recevoir les alertes liées à un document. */
interface ResponsableNotificationDocumentContract
{
    public function responsableNotification(): User;
}
