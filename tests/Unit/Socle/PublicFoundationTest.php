<?php

declare(strict_types=1);

namespace Tests\Unit\Socle;

use App\Modules\Socle\Contracts\AcademicCalendarContract;
use App\Modules\Socle\Contracts\AcademicContextContract;
use App\Modules\Socle\Contracts\AuditTrailContract;
use App\Modules\Socle\Contracts\AuthorizationContract;
use App\Modules\Socle\Contracts\DocumentVaultContract;
use App\Modules\Socle\Contracts\SchoolProfileContract;
use App\Modules\Socle\Data\AcademicYearData;
use App\Modules\Socle\Data\AuthorizationSubjectData;
use App\Modules\Socle\Enums\AcademicYearStatus;
use App\Modules\Socle\Enums\AuthorizationAction;
use App\Modules\Socle\Enums\AuthorizationSubjectType;
use App\Modules\Socle\Enums\PeriodSystem;
use DateTimeImmutable;
use ReflectionClass;
use Tests\TestCase;

class PublicFoundationTest extends TestCase
{
    public function test_authorization_uses_action_and_subject_enums_without_roles(): void
    {
        $subject = new AuthorizationSubjectData(AuthorizationSubjectType::RH_POSITION, 12);

        $this->assertSame('rh_position', $subject->type->value);
        $this->assertSame('execute', AuthorizationAction::EXECUTE->value);
        $this->assertFalse(str_contains((string) file_get_contents((new ReflectionClass(AuthorizationContract::class))->getFileName()), 'Role'));
    }

    public function test_academic_context_data_is_immutable_and_uses_the_public_statuses(): void
    {
        $year = new AcademicYearData(
            1,
            '2026-2027',
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2027-06-30'),
            AcademicYearStatus::CLOTURE_EN_COURS,
        );

        $this->assertTrue((new ReflectionClass($year))->isReadOnly());
        $this->assertSame('cloture_en_cours', $year->status->value);
        $this->assertSame(['semester', 'trimester'], array_column(PeriodSystem::cases(), 'value'));
    }

    public function test_public_contracts_do_not_expose_eloquent_models(): void
    {
        foreach ([
            AuthorizationContract::class,
            SchoolProfileContract::class,
            AcademicContextContract::class,
            AcademicCalendarContract::class,
            DocumentVaultContract::class,
            AuditTrailContract::class,
        ] as $contract) {
            $source = file_get_contents((new ReflectionClass($contract))->getFileName());

            $this->assertIsString($source);
            $this->assertStringNotContainsString('Illuminate\\Database\\Eloquent\\Model', $source);
            $this->assertStringNotContainsString('App\\Modules\\Socle\\Models', $source);
        }
    }

    public function test_socle_services_do_not_reach_other_domains_eloquent_models(): void
    {
        $files = glob(app_path('Modules/Socle/Services/*.php'));

        $this->assertIsArray($files);
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $source = file_get_contents($file);

            $this->assertIsString($source);
            foreach (['RH', 'Scolarite', 'Finances', 'Pedagogie', 'Communication', 'Logistique', 'Assiduite', 'VieScolaire'] as $domain) {
                $this->assertStringNotContainsString(
                    'Modules\\'.$domain.'\\Models',
                    $source,
                    basename($file).' ne doit pas dependre directement des Models du domaine '.$domain.'.',
                );
            }
        }
    }
}
