<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class TemplateNotificationInexistantException extends Exception
{
    public function __construct(string $canal, string $code)
    {
        parent::__construct("Aucun template de notification trouvé pour le canal '{$canal}' et le code '{$code}'.");
    }
}
