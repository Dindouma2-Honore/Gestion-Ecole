<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Jobs;

use App\Models\User;
use App\Modules\Pedagogie\Enums\StatutProgrammeEnum;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeAnalyse;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\Pedagogie\Models\ProgrammeLecon;
use App\Modules\Pedagogie\Services\AiAnalysisService;
use App\Modules\Pedagogie\Services\DocumentExtractorService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnalyzeProgrammeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $programmeId,
        public ?string $cheminFichier = null,
        public ?int $lanceParUserId = null,
    ) {}

    public function handle(
        DocumentExtractorService $extractor,
        AiAnalysisService $aiService
    ): void {
        $programme = Programme::find($this->programmeId);
        if ($programme === null) {
            Log::warning("AnalyzeProgrammeJob : programme [{$this->programmeId}] introuvable.");

            return;
        }

        // Création de l'enregistrement d'analyse
        $analyse = ProgrammeAnalyse::create([
            'programme_id' => $programme->id,
            'status' => 'en_cours',
            'ai_provider' => config('ai.provider', 'openai'),
            'ai_model' => config('ai.model', 'gpt-4o-mini'),
        ]);

        $programme->update([
            'statut' => StatutProgrammeEnum::ANALYSE_EN_COURS->value,
            'erreur_analyse' => null,
        ]);

        try {
            // Résolution du fichier physique
            $chemin = $this->resoudreCheminFichier($programme);
            if (empty($chemin) || ! file_exists($chemin)) {
                throw new \InvalidArgumentException("Aucun document physique exploitable trouvé pour le programme #{$programme->id}.");
            }

            Log::info("AnalyzeProgrammeJob : Début extraction pour programme #{$programme->id}", [
                'chemin' => $chemin,
                'taille' => filesize($chemin),
            ]);

            // Extraction du texte brut
            $texteExtrait = $extractor->extraireTexte($chemin);

            // Contexte pour l'IA
            $contexte = [
                'matiere' => $programme->matiere?->nom ?? 'Matière générale',
                'cycle' => $this->determinerCycle($programme),
                'niveau' => $programme->niveau_id ? "Niveau #{$programme->niveau_id}" : 'Général',
            ];

            // Analyse intelligente
            $resultatAi = $aiService->analyserProgrammeTexte($texteExtrait, $contexte);
            $donnees = $resultatAi['data'];

            // Enregistrement des résultats en base
            DB::transaction(function () use ($programme, $analyse, $texteExtrait, $resultatAi, $donnees, $chemin) {
                // Nettoyage préalable des éléments existants
                $programme->chapitres()->delete();

                $totalLecons = 0;
                $modules = $donnees['modules'] ?? [];

                foreach ($modules as $indexMod => $mod) {
                    $chapitre = ProgrammeChapitre::create([
                        'programme_id' => $programme->id,
                        'titre' => (string) ($mod['titre'] ?? 'Chapitre '.($indexMod + 1)),
                        'type_structure' => 'chapitre',
                        'ordre' => (int) ($mod['ordre'] ?? ($indexMod + 1)),
                        'objectif' => $mod['objectif'] ?? null,
                        'objectifs_pedagogiques' => $mod['objectif'] ?? null,
                        'volume_horaire_prevu' => (float) ($mod['volume_horaire'] ?? 0),
                    ]);

                    foreach ($mod['lecons'] ?? [] as $indexLec => $lec) {
                        ProgrammeLecon::create([
                            'programme_chapitre_id' => $chapitre->id,
                            'titre' => (string) ($lec['titre'] ?? 'Leçon '.($indexLec + 1)),
                            'type_element' => (string) ($lec['type_element'] ?? 'lecon'),
                            'ordre' => (int) ($lec['ordre'] ?? ($indexLec + 1)),
                            'objectifs' => $lec['objectifs'] ?? null,
                            'volume_horaire_prevu' => (float) ($lec['volume_horaire'] ?? 1.0),
                            'statut' => 'a_faire',
                        ]);
                        $totalLecons++;
                    }
                }

                // Mise à jour de l'enregistrement d'analyse
                $analyse->update([
                    'status' => 'termine',
                    'extracted_text' => config('ai.save_extracted_text', true) ? $texteExtrait : null,
                    'analysis_result' => $donnees,
                    'ai_provider' => $resultatAi['provider'],
                    'ai_model' => $resultatAi['model'],
                    'tokens_used' => $resultatAi['tokens_used'],
                    'analyzed_at' => now(),
                ]);

                // Mise à jour du programme
                $programme->update([
                    'statut' => StatutProgrammeEnum::A_VALIDER->value,
                    'erreur_analyse' => null,
                    'metadata_analyse' => [
                        'chapitres_extraits' => count($modules),
                        'lecons_extraites' => $totalLecons,
                        'date_analyse' => now()->toIso8601String(),
                        'fichier' => basename($chemin),
                        'ai_provider' => $resultatAi['provider'],
                        'ai_model' => $resultatAi['model'],
                        'summary' => $donnees['summary'] ?? null,
                        'incoherences_count' => count($donnees['inconsistencies'] ?? []),
                        'missing_info_count' => count($donnees['missing_information'] ?? []),
                    ],
                ]);
            });

            Log::info("AnalyzeProgrammeJob : Analyse réussie pour programme #{$programme->id}");

            $this->notifierUtilisateur(
                $programme,
                'Analyse intelligente terminée',
                'Le programme a été extrait et analysé par l’IA. Vous pouvez maintenant inspecter et valider la structure.',
                'success'
            );
        } catch (Throwable $e) {
            Log::error("AnalyzeProgrammeJob : Erreur lors de l'analyse du programme #{$programme->id}", [
                'erreur' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $analyse->update([
                'status' => 'echec',
                'error_message' => $e->getMessage(),
            ]);

            $programme->update([
                'statut' => StatutProgrammeEnum::ECHEC_ANALYSE->value,
                'erreur_analyse' => $e->getMessage(),
            ]);

            $this->notifierUtilisateur(
                $programme,
                'Échec de l\'analyse du programme',
                "L'analyse automatique a échoué : {$e->getMessage()}",
                'danger'
            );

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $programme = Programme::find($this->programmeId);
        if ($programme) {
            $msg = $exception?->getMessage() ?? 'Erreur inconnue lors du traitement du document.';
            $programme->update([
                'statut' => StatutProgrammeEnum::ECHEC_ANALYSE->value,
                'erreur_analyse' => $msg,
            ]);
        }
    }

    protected function resoudreCheminFichier(Programme $programme): ?string
    {
        // 1. Chemin explicite fourni au job
        if (! empty($this->cheminFichier) && file_exists($this->cheminFichier)) {
            return $this->cheminFichier;
        }

        // 2. Document attaché via DocumentService
        if (! empty($programme->document_source_id)) {
            $fichierPath = DB::table('documents')->where('id', $programme->document_source_id)->value('fichier_path');
            if (! empty($fichierPath)) {
                $cheminSurDisque = Storage::disk('documents')->path((string) $fichierPath);
                if (file_exists($cheminSurDisque)) {
                    return $cheminSurDisque;
                }
            }
        }

        // 3. Document source polymorphique
        $fichierPathPoly = DB::table('documents')
            ->where('documentable_type', Programme::class)
            ->where('documentable_id', $programme->id)
            ->where('categorie', 'programme_source')
            ->latest('id')
            ->value('fichier_path');

        if (! empty($fichierPathPoly)) {
            $cheminSurDisque = Storage::disk('documents')->path((string) $fichierPathPoly);
            if (file_exists($cheminSurDisque)) {
                return $cheminSurDisque;
            }
        }

        // 4. Fallback dans les répertoires standards
        if (! empty($programme->metadata_analyse['fichier'])) {
            $nomFichier = $programme->metadata_analyse['fichier'];
            $candidat = storage_path('app/private/documents/'.$nomFichier);
            if (file_exists($candidat)) {
                return $candidat;
            }
        }

        return null;
    }

    protected function determinerCycle(Programme $programme): string
    {
        $niveauId = $programme->niveau_id;
        if ($niveauId !== null) {
            if ($niveauId <= 3) {
                return 'Maternelle';
            }
            if ($niveauId <= 9) {
                return 'Primaire';
            }
        }

        return 'Secondaire';
    }

    protected function notifierUtilisateur(Programme $programme, string $titre, string $corps, string $type = 'info'): void
    {
        $cibleUserId = $this->lanceParUserId ?? $programme->enseignant_id;
        if (! $cibleUserId) {
            return;
        }

        $user = User::find($cibleUserId);
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
