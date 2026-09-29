<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Socle\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

class GovernanceSeeder extends Seeder
{
    public function run(): void
    {
        DocumentTemplate::query()->firstOrCreate(
            ['code' => 'BUL-SEC', 'version' => 1],
            [
                'nom' => 'Bulletin secondaire',
                'type_document' => 'bulletin',
                'module_proprietaire' => 'Pédagogie',
                'cycle' => 'secondaire',
                'contenu' => file_get_contents(app_path('Modules/Pedagogie/resources/views/bulletin.blade.php')),
                'mise_en_page' => ['logo' => 'images/logo.png', 'filigrane' => true],
                'orientation' => 'portrait',
                'format_papier' => 'A4',
                'actif' => true,
                'date_effet' => today(),
            ],
        );
    }
}
