<?php

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationTemplate extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['header_handle'];

    protected function casts(): array
    {
        return ['variables_schema' => 'array', 'buttons' => 'array', 'header_handle' => 'encrypted',
            'provider_response' => 'array', 'provider_components' => 'array',
            'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime',
            'last_synced_at' => 'datetime', 'last_webhook_at' => 'datetime', 'quality_updated_at' => 'datetime'];
    }

    public function history(): HasMany
    {
        return $this->hasMany(CommunicationTemplateEvent::class)->latest('id');
    }

    public function editable(): bool
    {
        return $this->meta_status === 'NOT_SUBMITTED' && $this->provider_template_id === null && $this->submission_state === null;
    }
}
