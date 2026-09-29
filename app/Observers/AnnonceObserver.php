<?php

namespace App\Observers;

use App\Mail\AnnonceNotificationMail;
use App\Models\User;
use App\Modules\Communication\Models\Annonce;
use Illuminate\Support\Facades\Mail;

class AnnonceObserver
{
    public function created(Annonce $annonce): void
    {
        try {
            $users = User::where('statut', 'actif')
                ->whereNotNull('email')
                ->get();

            foreach ($users as $user) {
                if ($user->email) {
                    Mail::to($user->email)->send(new AnnonceNotificationMail($annonce));
                }
            }
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer l'annonce par email: ".$e->getMessage());
        }
    }
}
