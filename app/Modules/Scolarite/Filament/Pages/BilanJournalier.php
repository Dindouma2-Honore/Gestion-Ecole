<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Pages;

use App\Modules\Scolarite\Contracts\BilanJournalierScolariteContract;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BilanJournalier extends Page
{
    protected string $view = 'scolarite::filament.pages.bilan-journalier';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Bilan journalier';

    protected static ?string $title = 'Bilan journalier de la scolarité';

    protected static ?string $slug = 'scolarite/bilan-journalier';

    public string $date = '';

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    /** @return array{bilan: object} */
    protected function getViewData(): array
    {
        return ['bilan' => $this->bilan()];
    }

    public function exporter(): StreamedResponse
    {
        $bilan = $this->bilan();

        return response()->streamDownload(function () use ($bilan): void {
            $sortie = fopen('php://output', 'w');
            fputcsv($sortie, ['Date', 'Reçu', 'Type de frais', 'Mode', 'Montant']);
            foreach ($bilan->paiements as $paiement) {
                fputcsv($sortie, [
                    $paiement->created_at,
                    $paiement->numero_recu,
                    $paiement->type_frais_libelle,
                    $paiement->mode,
                    $paiement->montant,
                ]);
            }
            fclose($sortie);
        }, "bilan-scolarite-{$this->date}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function bilan(): object
    {
        return app(BilanJournalierScolariteContract::class)->getBilan(CarbonImmutable::parse($this->date));
    }
}
