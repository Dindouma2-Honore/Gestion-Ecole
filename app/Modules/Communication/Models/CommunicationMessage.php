<?php

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_test' => 'boolean', 'payload' => 'encrypted:array', 'provider_response' => 'array'];
    }
}
