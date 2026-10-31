<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Models\CommunicationMessage;
use Illuminate\Support\Facades\DB;

class WhatsAppStatusService
{
    private const RANK = ['accepted' => 0, 'failed' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4];

    public function receive(string $id, string $status): void
    {
        if ($id === '' || strlen($id) > 255 || ! isset(self::RANK[$status])) {
            return;
        }
        DB::table('whatsapp_status_receipts')->insertOrIgnore(['provider_message_id' => $id, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        $this->reconcile($id);
    }

    public function reconcile(string $id): void
    {
        DB::transaction(function () use ($id): void {
            $m = CommunicationMessage::where('provider_message_id', $id)->lockForUpdate()->first();
            if (! $m) {
                return;
            }
            $next = $m->status;
            foreach (DB::table('whatsapp_status_receipts')->where('provider_message_id', $id)->pluck('status') as $status) {
                if ((self::RANK[$status] ?? -1) > (self::RANK[$next] ?? -1)) {
                    $next = $status;
                }
            }
            if ($next !== $m->status) {
                $m->update(['status' => $next]);
            }
        });
    }
}
