<?php

namespace App\Modules\Communication\Exceptions;

class WhatsAppTemplateNotApprovedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Le template doit être approuvé par Meta, synchronisé récemment et actif localement.');
    }
}
