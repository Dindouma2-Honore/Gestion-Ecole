<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Pages;

use App\Modules\Finances\Contracts\BalanceServiceContract;
use App\Modules\Finances\Exceptions\BalanceDepensesIndisponiblesException;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalancePeriode extends Page
{
    public static function getNavigationLabel(): string
    {
        return __('interface.balance');
    }

    protected string $view = 'finances::filament.pages.balance-periode';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $navigationLabel = 'Balance par période';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Balance des entrées et sorties';

    protected static ?string $slug = 'finances/balance';

    public string $dateDebut = '';

    public string $dateFin = '';

    public ?string $moduleOrigine = null;

    public ?string $typeMouvement = null;

    public function mount(): void
    {
        $this->dateDebut = now()->toDateString();
        $this->dateFin = now()->toDateString();
    }

    public function reinitialiserFiltres(): void
    {
        $this->dateDebut = now()->toDateString();
        $this->dateFin = now()->toDateString();
        $this->moduleOrigine = null;
        $this->typeMouvement = null;
    }

    /** @return array{balance: object, attente: Collection, erreur: ?string} */
    protected function getViewData(): array
    {
        $service = app(BalanceServiceContract::class);
        $debut = CarbonImmutable::parse($this->dateDebut);
        $fin = CarbonImmutable::parse($this->dateFin);
        $balance = $service->getBalance($debut, $fin, $this->moduleOrigine, $this->typeMouvement);

        try {
            $attente = $service->getSortiesEnAttenteValidation($debut, $fin);
            $erreur = null;
        } catch (BalanceDepensesIndisponiblesException $exception) {
            $attente = collect();
            $erreur = $exception->getMessage();
        }

        return ['balance' => $balance, 'attente' => $attente, 'erreur' => $erreur];
    }

    /** @return array<string> */
    public function modulesDisponibles(): array
    {
        return array_keys(app(BalanceServiceContract::class)->getBalance(
            CarbonImmutable::parse($this->dateDebut),
            CarbonImmutable::parse($this->dateFin),
        )->par_module);
    }

    public function exporter(): StreamedResponse
    {
        $balance = app(BalanceServiceContract::class)->getBalance(
            CarbonImmutable::parse($this->dateDebut),
            CarbonImmutable::parse($this->dateFin),
            $this->moduleOrigine,
            $this->typeMouvement,
        );

        return response()->streamDownload(function () use ($balance): void {
            $sortie = fopen('php://output', 'w');
            fputcsv($sortie, ['Type', 'Date', 'Module', 'Sous-module', 'Référence', 'Montant']);
            foreach ($balance->detail_entrees as $mouvement) {
                fputcsv($sortie, ['Entrée', $mouvement->created_at, $mouvement->module_origine, $mouvement->sous_module, ($mouvement->reference_type ?? '').' #'.($mouvement->reference_id ?? $mouvement->id), $mouvement->montant]);
            }
            foreach ($balance->detail_sorties as $mouvement) {
                fputcsv($sortie, ['Sortie', $mouvement->created_at, $mouvement->module_origine, $mouvement->sous_module, ($mouvement->reference_type ?? '').' #'.($mouvement->reference_id ?? $mouvement->id), $mouvement->montant]);
            }
            fclose($sortie);
        }, "balance-{$this->dateDebut}-{$this->dateFin}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
