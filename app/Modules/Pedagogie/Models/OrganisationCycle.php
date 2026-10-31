<?php

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;

class OrganisationCycle extends Model
{
    protected $table = 'organisation_pedagogique_cycles';

    protected $fillable = ['cycle_id', 'mode'];
}
