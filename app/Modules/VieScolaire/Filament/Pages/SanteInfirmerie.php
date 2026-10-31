<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Pages;

use App\Modules\VieScolaire\Filament\Resources\DossierSanteResource;
use App\Modules\VieScolaire\Filament\Resources\VisiteInfirmerieResource;

class SanteInfirmerie extends VieScolaireSection
{
    protected static ?string $slug = 'vie-scolaire/sante-infirmerie';

    protected static ?string $title = 'Santé & infirmerie';

    public function getSectionDescription(): string
    {
        return 'Données médicales protégées, urgences et passages à l’infirmerie.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Dossiers de santé', 'description' => 'Informations critiques et contacts d’urgence', 'icon' => 'heroicon-o-heart', 'url' => DossierSanteResource::getUrl()],
            ['label' => 'Visites à l’infirmerie', 'description' => 'Symptômes, soins, décision et notification parent', 'icon' => 'heroicon-o-plus-circle', 'url' => VisiteInfirmerieResource::getUrl()],
        ];
    }
}
