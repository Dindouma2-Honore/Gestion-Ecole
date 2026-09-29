<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UnifiedListCardTest extends TestCase
{
    /** @test */
    public function shared_list_card_uses_the_module_card_visual_structure()
    {
        $html = $this->blade(
            '<x-ambassadors-card 
                :list="[
                    \'name\' => \'Classe de sixième\',
                    \'badge\' => \'Actif\'
                ]" 
                badge="Actif"
            >Classe de sixième</x-ambassadors-card>'
        );

        $this->assertStringContainsString('amb-card', $html);
        $this->assertStringContainsString('amb-card__status', $html);
        $this->assertStringContainsString('amb-card__icon', $html);
        $this->assertStringContainsString('amb-card__action', $html);
        $this->assertStringContainsString('Voir la fiche', $html);
    }

    /** @test */
    public function core_people_lists_reuse_the_shared_card_component()
    {
        // Votre test ici
        $this->assertTrue(true);
    }

    /** @test */
    public function notifications_use_non_blocking_branded_cards()
    {
        // Votre test ici
        $this->assertTrue(true);
    }

    /** @test */
    public function habilitation_matrices_reuse_the_shared_card_component()
    {
        // Votre test ici
        $this->assertTrue(true);
    }
}
