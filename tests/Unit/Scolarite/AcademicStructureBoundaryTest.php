<?php

declare(strict_types=1);

use App\Modules\Scolarite\Contracts\AcademicStructureCatalogContract;
use App\Modules\Scolarite\Contracts\AcademicStructureManagementContract;
use App\Modules\Scolarite\Contracts\AcademicStructureReadContract;
use App\Modules\Scolarite\Data\AcademicStructureItemDTO;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Services\AcademicStructureService;
use Symfony\Component\Yaml\Yaml;

it('exposes only scalar inputs readonly DTOs and documented DTO arrays', function (): void {
    $seen = [];
    foreach ([AcademicStructureReadContract::class, AcademicStructureCatalogContract::class, AcademicStructureManagementContract::class] as $contract) {
        foreach ((new ReflectionClass($contract))->getMethods() as $method) {
            expect($seen)->not->toContain($method->name);
            $seen[] = $method->name;
            $return = $method->getReturnType();
            expect($return)->toBeInstanceOf(ReflectionNamedType::class);
            expect($return->getName())->toBeIn(['array', AcademicStructureItemDTO::class]);
            if ($return->getName() === 'array') {
                expect($method->getDocComment())->toContain('array<int, AcademicStructureItemDTO>');
            }
            foreach ($method->getParameters() as $parameter) {
                expect($parameter->getType()->getName())->toBeIn(['int', 'string', 'bool']);
            }
        }
    }
    $dto = new ReflectionClass(AcademicStructureItemDTO::class);
    expect($dto->isReadOnly())->toBeTrue();
    foreach ($dto->getProperties() as $property) {
        expect($property->getType()->getName())->toBeIn(['int', 'string', 'bool']);
    }
});

it('classifies Contracts and Data as public and Models and Services as internal in Deptrac', function (): void {
    $config = Yaml::parseFile(base_path('deptrac.yaml'))['parameters'];
    $layers = array_column($config['layers'], null, 'name');
    $public = $layers['ScolariteContracts']['collectors'][0]['regex'];
    $internal = $layers['ScolariteInternal']['collectors'][0]['regex'];
    foreach ([AcademicStructureReadContract::class, AcademicStructureItemDTO::class] as $class) {
        expect(preg_match('~'.$public.'~', $class))->toBe(1)
            ->and(preg_match('~'.$internal.'~', $class))->toBe(0);
    }
    foreach ([Cycle::class, AcademicStructureService::class] as $class) {
        expect(preg_match('~'.$public.'~', $class))->toBe(0)
            ->and(preg_match('~'.$internal.'~', $class))->toBe(1);
    }
    foreach (['TechniqueInternal', 'LogistiqueInternal', 'SocleInternal', 'PedagogieInternal', 'RHInternal', 'FinancesInternal', 'AssiduiteInternal', 'CommunicationInternal'] as $consumer) {
        expect($config['ruleset'][$consumer])->toContain('ScolariteContracts')->not->toContain('ScolariteInternal');
    }
});

it('has no production consumer of legacy reference models or external reference model imports', function (): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (str_contains($path, '/Modules/Scolarite/') || str_contains($path, '/database/')) {
            continue;
        }
        $source = file_get_contents($path);
        foreach (['Cycle', 'Niveau', 'Filiere', 'SectionScolaire'] as $name) {
            expect($source)->not->toContain('App\\Modules\\Socle\\Models\\'.$name);
            expect($source)->not->toContain('App\\Modules\\Scolarite\\Models\\'.$name);
        }
        expect($source)->not->toContain('ReferentielScolaireRead');
    }
    foreach (['Cycle', 'Niveau', 'Filiere', 'SectionScolaire'] as $name) {
        expect(file_exists(app_path('Modules/Socle/Models/'.$name.'.php')))->toBeFalse();
    }
    expect(file_exists(app_path('Modules/Socle/Models/Filiere.php')))->toBeFalse()
        ->and(file_exists(app_path('Modules/Socle/Contracts/ReferentielScolaireReadContract.php')))->toBeFalse()
        ->and(file_exists(app_path('Modules/Socle/Services/ReferentielScolaireReadService.php')))->toBeFalse();
});
