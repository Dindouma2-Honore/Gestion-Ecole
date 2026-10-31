<?php

use App\Modules\Socle\Contracts\WhatsAppTestTemplateCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (WhatsAppTestTemplateCatalog::CODES as $code) {
            DB::table('templates_notifications')->insertOrIgnore(['code' => $code, 'canal' => 'whatsapp', 'sujet' => str_replace('_', ' ', $code), 'corps' => 'Modèle local à rédiger et valider.', 'contenu' => 'Modèle local à rédiger et valider.', 'actif' => false, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('whatsapp_template_metadata')->insertOrIgnore(['code' => $code, 'status' => 'LOCAL_ONLY', 'language' => 'fr', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    { /* Preserve editorial data and provider history. */
    }
};
