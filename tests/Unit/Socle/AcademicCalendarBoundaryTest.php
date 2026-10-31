<?php

declare(strict_types=1);

namespace Tests\Unit\Socle;

use Tests\TestCase;

class AcademicCalendarBoundaryTest extends TestCase
{
    public function test_assiduite_and_vie_scolaire_calendar_services_do_not_import_socle_models(): void
    {
        foreach ([
            base_path('app/Modules/Assiduite/Services/AttendanceEngine.php'),
            base_path('app/Modules/VieScolaire/Services/InstitutionalCalendarService.php'),
        ] as $file) {
            $source = file_get_contents($file);

            $this->assertIsString($source);
            $this->assertStringNotContainsString('App\\Modules\\Socle\\Models\\', $source);
        }
    }
}
