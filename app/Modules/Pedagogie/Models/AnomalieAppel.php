<?php

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;

class AnomalieAppel extends Model
{
    protected $table = 'anomalies_appel';

    protected $fillable = ['seance_id', 'detectee_le', 'notifiee', 'resolue'];

    protected $casts = [
        'detectee_le' => 'datetime',
        'notifiee' => 'boolean',
        'resolue' => 'boolean',
    ];

    public function seance()
    {
        return $this->belongsTo(Seance::class);
    }
}
