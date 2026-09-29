<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Pages;

use App\Models\User;
use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SituationFinanciere extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'finances::filament.pages.situation-financiere';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $navigationLabel = 'Situation des élèves';

    protected static ?string $title = 'Situation financière des élèves';

    protected static ?string $slug = 'finances/situation-eleves';

    public ?int $niveauId = null;

    public ?int $classeId = null;

    public ?string $sexe = null;

    public string $recherche = '';

    public array $colonnesExport = ['eleve', 'matricule', 'classe', 'niveau', 'sexe', 'du', 'paye', 'reste', 'statut', 'telephone_parent'];

    protected function getViewData(): array
    {
        return ['lignes' => $this->lignes(), 'niveaux' => $this->niveaux(), 'classes' => $this->classes(), 'totauxParFrais' => $this->totauxParFrais()];
    }

    public function exporterCsv(): StreamedResponse
    {
        $lignes = $this->lignes();

        return response()->streamDownload(function () use ($lignes): void {
            $sortie = fopen('php://output', 'w');
            $definitions = $this->colonnesDisponibles();
            fputcsv($sortie, collect($this->colonnesExport)->map(fn ($code) => $definitions[$code])->all());
            foreach ($lignes as $ligne) {
                fputcsv($sortie, collect($this->colonnesExport)->map(fn ($code) => $ligne[$code] ?? '')->all());
            }
            fclose($sortie);
        }, 'situation-financiere-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exporterPdf(): StreamedResponse
    {
        $contenu = Pdf::loadView('finances::pdf.situation-financiere', ['lignes' => $this->lignes(), 'colonnes' => $this->colonnesExport, 'definitions' => $this->colonnesDisponibles()])->output();

        return response()->streamDownload(static fn () => print ($contenu), 'situation-financiere-'.today()->format('Y-m-d').'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole(['Fondateur', 'Comptable']) ?? false;
    }

    private function lignes(): array
    {
        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
        $eleves = app(EleveServiceInterface::class)->getElevesPourSituationFinanciere($this->niveauId, $this->classeId, $this->sexe);
        $recherche = mb_strtolower(trim($this->recherche));

        return collect($eleves)->filter(fn (array $eleve): bool => $recherche === '' || str_contains(mb_strtolower($eleve['nom'].' '.$eleve['prenom']), $recherche))
            ->map(function (array $eleve) use ($anneeId): array {
                $du = app(FraisScolaireServiceContract::class)->getMontantDu($eleve['id'], $anneeId);
                $paye = (float) Paiement::query()->where('eleve_id', $eleve['id'])->where('annee_scolaire_id', $anneeId)->where('statut', 'valide')->sum('montant');
                $reste = round(max(0, $du - $paye), 2);

                return [...$eleve, 'eleve' => $eleve['prenom'].' '.$eleve['nom'], 'du' => $du, 'paye' => $paye,
                    'reste' => $reste, 'statut' => $reste <= 0 ? 'soldé' : ($paye > 0 ? 'partiel' : 'impayé')];
            })->values()->all();
    }

    private function niveaux(): array
    {
        return collect(app(ParametrageServiceContract::class)->getTousLesNiveaux())->pluck('nom', 'id')->all();
    }

    public function colonnesDisponibles(): array
    {
        return ['eleve' => 'Nom élève', 'matricule' => 'Matricule', 'classe' => 'Classe', 'niveau' => 'Niveau', 'sexe' => 'Sexe', 'du' => 'Montant attendu', 'paye' => 'Montant reçu', 'reste' => 'Montant restant', 'statut' => 'Statut', 'telephone_parent' => 'Téléphone parent'];
    }

    private function totauxParFrais(): array
    {
        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
        $totaux = [];
        foreach ($this->lignes() as $ligne) {
            foreach (app(FraisScolaireServiceContract::class)->getSituationParFrais($ligne['id'], $anneeId) as $code => $frais) {
                $totaux[$code] ??= ['libelle' => $frais['libelle'], 'attendu' => 0.0, 'recu' => 0.0, 'restant' => 0.0];
                foreach (['attendu', 'recu', 'restant'] as $champ) {
                    $totaux[$code][$champ] += $frais[$champ];
                }
            }
        }

        return $totaux;
    }

    private function classes(): array
    {
        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();

        return collect(app(ClasseServiceInterface::class)->getToutesLesClasses($anneeId))->pluck('nom', 'id')->all();
    }
}
