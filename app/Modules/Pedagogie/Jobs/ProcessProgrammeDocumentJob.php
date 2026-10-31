<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Jobs;

use App\Models\User;
use App\Modules\Pedagogie\Enums\StatutProgrammeEnum;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\Pedagogie\Models\ProgrammeLecon;
use App\Modules\Pedagogie\Services\ProgramDocumentAnalyzer;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessProgrammeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $programmeId,
        public string $cheminFichier,
        public ?string $cycle = null,
    ) {}

    public function handle(ProgramDocumentAnalyzer $analyzer): void
    {
        $programme = Programme::find($this->programmeId);
        if ($programme === null) {
            Log::warning("ProcessProgrammeDocumentJob : programme [{$this->programmeId}] introuvable.");

            return;
        }

        $programme->update([
            'statut' => StatutProgrammeEnum::ANALYSE_EN_COURS->value,
            'erreur_analyse' => null,
        ]);

        try {
            $structure = $analyzer->analyserFichier($this->cheminFichier, $this->cycle);

            DB::transaction(function () use ($programme, $structure) {
                // Nettoyage préalable des éventuels éléments existants pour ce programme
                $programme->chapitres()->delete();

                $totalLecons = 0;
                foreach ($structure as $indexChap => $chapData) {
                    $chapitre = ProgrammeChapitre::create([
                        'programme_id' => $programme->id,
                        'titre' => $chapData['titre'],
                        'type_structure' => $chapData['type_structure'] ?? 'chapitre',
                        'ordre' => (int) ($chapData['ordre'] ?? ($indexChap + 1)),
                        'objectif' => $chapData['objectif'] ?? null,
                        'objectifs_pedagogiques' => $chapData['objectif'] ?? null,
                        'contenu' => $chapData['contenu'] ?? null,
                        'volume_horaire_prevu' => $chapData['volume_horaire_prevu'] ?? null,
                    ]);

                    foreach ($chapData['lecons'] ?? [] as $indexLecon => $leconData) {
                        ProgrammeLecon::create([
                            'programme_chapitre_id' => $chapitre->id,
                            'titre' => $leconData['titre'],
                            'type_element' => $leconData['type_element'] ?? 'lecon',
                            'ordre' => (int) ($leconData['ordre'] ?? ($indexLecon + 1)),
                            'objectifs' => $leconData['objectifs'] ?? null,
                            'volume_horaire_prevu' => (float) ($leconData['volume_horaire_prevu'] ?? 1.0),
                            'statut' => 'a_faire',
                        ]);
                        $totalLecons++;
                    }
                }

                $programme->update([
                    'statut' => StatutProgrammeEnum::A_VALIDER->value,
                    'metadata_analyse' => [
                        'chapitres_extraits' => count($structure),
                        'lecons_extraites' => $totalLecons,
                        'date_analyse' => now()->toIso8601String(),
                        'fichier' => basename($this->cheminFichier),
                    ],
                ]);
            });

            $this->notifierEnseignant(
                $programme,
                'Analyse de votre programme terminée avec succès',
                'La structure a été extraite du document. Veuillez la vérifier et la valider avant activation.',
                'success'
            );
        } catch (Throwable $e) {
            $this->gererEchec($programme, $e->getMessage());
            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $programme = Programme::find($this->programmeId);
        if ($programme) {
            $this->gererEchec($programme, $exception?->getMessage() ?? 'Erreur inconnue lors du traitement du document.');
        }
    }

    private function gererEchec(Programme $programme, string $erreur): void
    {
        $programme->update([
            'statut' => StatutProgrammeEnum::ECHEC_ANALYSE->value,
            'erreur_analyse' => $erreur,
        ]);

        $this->notifierEnseignant(
            $programme,
            'Échec de l\'analyse automatique du programme',
            "Le document n'a pas pu être extrait automatiquement ({$erreur}). Vous pouvez réessayer avec un document texte ou utiliser la saisie manuelle.",
            'danger'
        );
    }

    private function notifierEnseignant(Programme $programme, string $titre, string $corps, string $type = 'info'): void
    {
        if ($programme->enseignant_id === null) {
            return;
        }

        $user = User::find($programme->enseignant_id);
        if ($user !== null && class_exists(Notification::class)) {
            $notif = Notification::make()
                ->title($titre)
                ->body($corps);

            match ($type) {
                'success' => $notif->success(),
                'danger' => $notif->danger(),
                default => $notif->info(),
            };

            $notif->sendToDatabase($user);
        }
    }
}
