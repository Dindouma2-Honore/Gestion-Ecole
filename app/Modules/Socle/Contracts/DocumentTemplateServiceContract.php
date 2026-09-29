<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

interface DocumentTemplateServiceContract
{
    public function getTemplateActif(string $code): object;

    public function publierNouvelleVersion(string $code, array $attributs, ?int $createdBy = null): object;

    public function render(object $template, array $donnees): string;
}
