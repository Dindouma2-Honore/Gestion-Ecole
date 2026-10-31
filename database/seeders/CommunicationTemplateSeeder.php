<?php

namespace Database\Seeders;

use App\Modules\Communication\Models\CommunicationTemplate;
use App\Modules\Communication\Support\WhatsAppTemplateAudit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommunicationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $waba = (string) config('whatsapp.waba_id');
        if ($waba === '') {
            return;
        }
        $rows = json_decode(file_get_contents(app_path('Modules/Communication/Support/initial-whatsapp-templates.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($rows as $data) {
            DB::transaction(function () use ($data, $waba) {
                $template = CommunicationTemplate::firstOrCreate(['waba_id' => $waba, 'code' => $data['code'], 'language' => $data['language']], $data);
                if ($template->wasRecentlyCreated) {
                    WhatsAppTemplateAudit::record($template, 'template_created');
                }
            });
        }
    }
}
