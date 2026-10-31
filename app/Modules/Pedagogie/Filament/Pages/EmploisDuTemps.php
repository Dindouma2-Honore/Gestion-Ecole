<?php
namespace App\Modules\Pedagogie\Filament\Pages;
use App\Modules\Pedagogie\Filament\Resources\{CreneauHoraireResource,EmploiDuTempsResource};
class EmploisDuTemps extends PedagogieSection {
 protected static ?string $slug='pedagogie/emplois-du-temps'; protected static ?string $title='Emplois du temps';
 public function getSectionDescription(): string { return 'Organisez la grille théorique des cours et contrôlez les conflits.'; }
 public function getSectionLinks(): array { return [
 ['label'=>'Grille des cours','description'=>'Vues par classe, enseignant et salle','icon'=>'heroicon-o-calendar-days','url'=>EmploiDuTempsResource::getUrl()],
 ['label'=>'Créneaux horaires','description'=>'Jours et plages de cours','icon'=>'heroicon-o-clock','url'=>CreneauHoraireResource::getUrl()],
 ]; }
}
