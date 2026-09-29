<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;

class SectionScolaire extends Model
{
    protected $table = 'sections_scolaires';

    protected $guarded = [];

    protected $casts = ['actif' => 'boolean'];
}
