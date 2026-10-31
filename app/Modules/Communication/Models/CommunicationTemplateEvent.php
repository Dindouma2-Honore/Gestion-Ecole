<?php

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationTemplateEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'result' => 'array'];
    }
}
