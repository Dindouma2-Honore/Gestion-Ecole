<?php

namespace App\Observers;

use App\Mail\MessageParentMail;
use App\Models\User;
use App\Modules\Communication\Models\MessageParent;
use App\Modules\Scolarite\Models\ParentTuteur;
use Illuminate\Support\Facades\Mail;

class MessageParentObserver
{
    public function created(MessageParent $messageParent): void
    {
        try {
            $emailsFromTable = ParentTuteur::whereNotNull('email')->pluck('email')->toArray();
            $emailsFromUsers = User::role('Parent')->whereNotNull('email')->pluck('email')->toArray();

            $allEmails = array_values(array_unique(array_filter(array_merge($emailsFromTable, $emailsFromUsers))));

            foreach ($allEmails as $email) {
                Mail::to($email)->send(new MessageParentMail($messageParent));
            }
        } catch (\Throwable $e) {
            logger()->error("Impossible d'envoyer le message parents aux destinataires: ".$e->getMessage());
        }
    }
}
