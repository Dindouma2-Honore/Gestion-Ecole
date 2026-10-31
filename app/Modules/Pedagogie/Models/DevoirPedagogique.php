<?php

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;

final class DevoirPedagogique extends Model
{
    protected $table = 'devoirs_pedagogiques';

    protected $guarded = ['id'];
}
