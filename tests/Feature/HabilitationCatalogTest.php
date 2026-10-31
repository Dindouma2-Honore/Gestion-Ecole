<?php

namespace Tests\Feature;

use App\Filament\Support\HabilitationCatalog;
use App\Filament\Support\ModuleAccess;
use Tests\TestCase;

class HabilitationCatalogTest extends TestCase
{
    public function test_every_concrete_resource_has_a_catalogued_feature(): void
    {
        $codes = array_column(HabilitationCatalog::features(), 'code');
        foreach (glob(app_path('Modules/*/Filament/Resources/*.php')) as $file) {
            $class = 'App\\'.str_replace('/', '\\', substr($file, strlen(app_path()) + 1, -4));
            $feature = HabilitationCatalog::featureFor($class);
            $this->assertContains($feature, $codes, $class);
            $this->assertSame($feature, ModuleAccess::featureCodeFromComponent($class.'\\Pages\\EditRecord'));
        }
    }

    public function test_similar_resource_names_do_not_cross_module_boundaries(): void
    {
        foreach ([
            ['Scolarite', 'NiveauResource', 'socle.parametres'],
            ['Pedagogie', 'ConseilClasseResource', 'pedagogie.bulletins'],
            ['VieScolaire', 'SortieEleveResource', 'viescolaire.sorties'],
            ['Communication', 'CommunicationTemplateResource', 'communication.templates'],
            ['RH', 'PersonnelPrimeResource', 'rh.paie'],
        ] as [$module, $resource, $expected]) {
            $this->assertSame($expected, ModuleAccess::featureCodeFromComponent("App\\Modules\\{$module}\\Filament\\Resources\\{$resource}"));
        }
    }
}
