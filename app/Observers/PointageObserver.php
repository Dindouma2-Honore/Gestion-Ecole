<?php

namespace App\Observers;

use App\Mail\PointageConfirmationMail;
use App\Models\User;
use App\Modules\RH\Models\Pointage;
use Illuminate\Support\Facades\Mail;

class PointageObserver
{
    public function created(Pointage $pointage): void
    {
        try {
            $destinataires = User::role(['Fondateur', 'Directeur'])
                ->where('statut', 'actif')
                ->whereNotNull('email')
                ->pluck('email')
                ->toArray();

            if (empty($destinataires)) {
                $destinataires = User::where('statut', 'actif')
                    ->whereNotNull('email')
                    ->take(5)
                    ->pluck('email')
                    ->toArray();
            }

            foreach ($destinataires as $email) {
                if ($email) {
                    Mail::to($email)->send(new PointageConfirmationMail($pointage));
                }
            }
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer les emails de confirmation de pointage: ".$e->getMessage());
        }
    }
}
