<?php

namespace App\Modules\Pedagogie\Filament\Pages;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource;
use App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource;
use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource;
use App\Modules\Pedagogie\Models\OrganisationCycle;
use App\Modules\Pedagogie\Services\OrganisationCycleService;
use App\Modules\Pedagogie\Services\OrganisationPedagogiqueContext;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class Enseignements extends PedagogieSection
{
    protected static ?string $slug = 'pedagogie/enseignements';

    protected static ?string $title = 'Organisation pédagogique';

    public function getSectionDescription(): string
    {
        return 'Organisez les matières et domaines, l’offre annuelle et les responsabilités des enseignants.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Matières & domaines', 'description' => 'Catalogue commun de matières, domaines et activités', 'icon' => 'heroicon-o-book-open', 'url' => MatiereResource::getUrl()],
            ['label' => 'Catégories', 'description' => 'Classement du catalogue pédagogique', 'icon' => 'heroicon-o-tag', 'url' => CategorieMatiereResource::getUrl()],
            ['label' => 'Offre pédagogique', 'description' => 'Matières et coefficients annuels par niveau ou classe', 'icon' => 'heroicon-o-adjustments-horizontal', 'url' => OffrePedagogiqueResource::getUrl()],
            ['label' => 'Affectations pédagogiques', 'description' => 'Principal, assistants et enseignants de matière', 'icon' => 'heroicon-o-user-plus', 'url' => AffectationPedagogiqueResource::getUrl()],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [AmbassadorsDesign::goldenFormModal(Action::make('organisationCycles')->label('Organisation des cycles'))
            ->authorize(fn () => app(OrganisationCycleService::class)->autorise())
            ->fillForm(fn () => ['cycles' => collect(app(OrganisationPedagogiqueContext::class)->cycles())->map(fn ($c) => [
                'cycle_id' => $c['id'], 'nom' => $c['nom'], 'mode' => OrganisationCycle::where('cycle_id', $c['id'])->value('mode'),
            ])->values()->all()])
            ->schema([Repeater::make('cycles')->label('Mode d’affectation par cycle')->addable(false)->deletable(false)->reorderable(false)->schema([
                Hidden::make('cycle_id')->required(), TextInput::make('nom')->label('Cycle')->disabled()->dehydrated(false),
                Select::make('mode')->label('Organisation')->options(['classe' => 'Principal et assistants par classe', 'matiere' => 'Enseignants par matière'])->required(),
            ])])->action(fn (array $data) => app(OrganisationCycleService::class)->enregistrer($data['cycles']))];
    }
}
