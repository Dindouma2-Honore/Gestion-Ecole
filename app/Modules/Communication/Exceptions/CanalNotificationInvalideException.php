<?php

declare(strict_types=1);

namespace App\Modules\Communication\Exceptions;

use Exception;

class CanalNotificationInvalideException extends Exception
{
    public function __construct(string $canal)
    {
        parent::__construct("Le canal de notification '{$canal}' n'est pas supporté. Canaux valides : whatsapp, sms, email, in_app.");
    }
}
