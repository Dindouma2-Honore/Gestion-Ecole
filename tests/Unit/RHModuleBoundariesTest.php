<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Exceptions\TransitionStatutCongeInvalideException;
use App\Modules\RH\Models\Conge;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class RHModuleBoundariesTest extends TestCase
{
    public function test_conge_uses_its_own_workflow_exception(): void
    {
        $conge = new Conge(['statut' => 'demande']);

        $this->expectException(TransitionStatutCongeInvalideException::class);

        $conge->changerStatut('termine');
    }

    public function test_paie_contract_does_not_expose_an_internal_model(): void
    {
        $returnType = (new ReflectionMethod(PaieServiceContract::class, 'calculerBulletin'))
            ->getReturnType()?->getName();

        $this->assertSame('object', $returnType);
    }
}
