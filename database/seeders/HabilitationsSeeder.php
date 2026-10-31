<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Filament\Support\HabilitationCatalog;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use Illuminate\Database\Seeder;

class HabilitationsSeeder extends Seeder
{
    public function run(): void
    {
        app(HabilitationServiceContract::class)->synchroniserCatalogue(
            HabilitationCatalog::features(),
        );
    }
}
