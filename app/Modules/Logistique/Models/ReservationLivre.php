<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationLivre extends Model
{
    protected $table = 'reservations_livres';

    public $timestamps = false;

    protected $fillable = [
        'livre_id', 'emprunteur_type', 'emprunteur_id', 'date_reservation', 'statut',
    ];

    protected $casts = [
        'date_reservation' => 'datetime',
    ];
}
