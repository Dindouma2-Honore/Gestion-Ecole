<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Vite est validé séparément par `npm run build` dans la CI. Les tests
        // HTTP vérifient ici le comportement Laravel et le rendu Blade sans
        // dépendre de fichiers compilés présents sur le disque.
        $this->withoutVite();
    }
}
