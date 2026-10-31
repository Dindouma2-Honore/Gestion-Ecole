<?php

namespace App\Modules\Communication\Policies;

use App\Models\User;
use App\Modules\Communication\Models\CommunicationTemplate;

class CommunicationTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->statut === 'actif' && $user->can('view_whatsapp_templates');
    }

    public function view(User $user, CommunicationTemplate $template): bool
    {
        return $this->viewAny($user) && $template->waba_id === (string) config('whatsapp.waba_id');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->can('create_whatsapp_template');
    }

    public function update(User $user, CommunicationTemplate $template): bool
    {
        return $this->view($user, $template) && $user->can('update_whatsapp_template') && $template->editable();
    }

    public function delete(User $user, CommunicationTemplate $template): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
