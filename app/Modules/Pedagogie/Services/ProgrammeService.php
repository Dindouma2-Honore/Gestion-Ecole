<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Enums\StatutProgrammeEnum;
use App\Modules\Pedagogie\Exceptions\EcritureAnneeNonAutoriseeException;
use App\Modules\Pedagogie\Exceptions\MatiereIntrouvableException;
use App\Modules\Pedagogie\Jobs\AnalyzeProgrammeJob;
use App\Modules\Pedagogie\Jobs\ProcessProgrammeDocumentJob;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\Pedagogie\Models\ProgrammeLecon;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\RH\Contracts\EnseignantServiceInterface;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProgrammeService implements ProgrammeServiceInterface
{
    public function __construct(
        private readonly AnneeScolaireServiceContract $anneeScolaire,
        private readonly ?ClasseServiceInterface $classeService = null,
        private readonly ?EnseignantServiceInterface $enseignantService = null,
        private readonly ?DocumentServiceContract $documentService = null,
        private readonly ?AuditServiceContract $auditService = null,
    ) {}

    public function getProgrammesParNiveau(int $niveauId, int $anneeScolaireId): array
    {
        return Programme::where('niveau_id', $niveauId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->orderBy('ordre')
            ->get()
            ->map(fn (Programme $programme) => [
                'id' => $programme->id,
                'titre' => $programme->titre,
                'matiere_id' => $programme->matiere_id,
                'statut' => $programme->statut,
            ])
            ->all();
    }

    public function creerProgramme(
        int $matiereId,
        int $niveauId,
        int $anneeScolaireId,
        string $titre,
        ?string $description = null
    ): array {
        if (! Matiere::whereKey($matiereId)->exists()) {
            throw MatiereIntrouvableException::pourId($matiereId);
        }

        if (! $this->anneeScolaire->ecritureAutorisee($anneeScolaireId, Auth::user())) {
            throw new EcritureAnneeNonAutoriseeException($anneeScolaireId);
        }

        $programme = Programme::create([
            'matiere_id' => $matiereId,
            'niveau_id' => $niveauId,
            'annee_scolaire_id' => $anneeScolaireId,
            'source' => 'officiel',
            'titre' => $titre,
            'description' => $description,
            'statut' => 'brouillon',
        ]);

        return $programme->toArray();
    }

    public function publier(int $programmeId): array
    {
        $programme = Programme::findOrFail($programmeId);
        abort_if($programme->analyse_statut !== null && $programme->statut !== 'valide', 422, 'Validez d’abord la structure du programme.');
        $programme->update(['statut' => 'publie']);

        return $programme->toArray();
    }

    public function soumettreProgramme(
        int $enseignantId,
        int $matiereId,
        int $classeId,
        int $anneeScolaireId,
        UploadedFile $fichierSource,
        array $chapitres,
        ?int $offrePedagogiqueId = null,
    ): object {
        $access = app(ProgrammeAccessService::class);
        abort_unless($access->enseignantId() === $enseignantId, 403);
        $offres = collect($access->offres($anneeScolaireId));
        $contexte = $offres->first(fn ($o) => (int) $o['classe_id'] === $classeId && (int) $o['matiere_id'] === $matiereId && ($offrePedagogiqueId === null || (int) $o['offre_id'] === $offrePedagogiqueId));
        abort_unless($contexte, 403);

        return app(ProgramImportService::class)->importer($anneeScolaireId, $classeId, (int) $contexte['offre_id'], $fichierSource);
    }

    public function importerProgrammeDocument(
        int $enseignantId,
        int $offrePedagogiqueId,
        UploadedFile $fichier,
    ): object {
        $anneeScolaireId = $this->anneeScolaire->getAnneeCouranteId();

        $offre = OffrePedagogique::query()
            ->with('matiere')
            ->whereKey($offrePedagogiqueId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->firstOrFail();
        $this->verifierAffectationOffrePourActeur($enseignantId, $offre, $anneeScolaireId);

        $cycle = 'secondaire';
        $niveauId = $offre->niveau_id;
        if ($this->classeService !== null && $offre->classe_id !== null) {
            try {
                $niveauId = $this->classeService->getNiveauId($offre->classe_id) ?? $niveauId;
            } catch (\Throwable) {
                // Si la classe n'est pas trouvée (ex: tests ou environnement partiel), fallback sur $offre->niveau_id
            }
        }
        if ($niveauId !== null) {
            if ($niveauId <= 3) {
                $cycle = 'maternelle';
            } elseif ($niveauId <= 9) {
                $cycle = 'primaire';
            }
        }

        [$programme, $cheminAbsolu] = DB::transaction(function () use ($offre, $offrePedagogiqueId, $anneeScolaireId, $enseignantId, $fichier): array {
            $programme = Programme::create([
                'matiere_id' => $offre->matiere_id,
                'offre_pedagogique_id' => $offrePedagogiqueId,
                'niveau_id' => $offre->niveau_id ?? 1,
                'classe_id' => $offre->classe_id,
                'annee_scolaire_id' => $anneeScolaireId,
                'source' => 'enseignant',
                'enseignant_id' => $enseignantId,
                'titre' => 'Programme - '.($offre->matiere->nom ?? 'Matière').($offre->classe_id ? ' (Classe #'.$offre->classe_id.')' : ''),
                'statut' => StatutProgrammeEnum::ANALYSE_EN_ATTENTE->value,
                'version' => 1,
            ]);

            $cheminAbsolu = null;
            if ($this->documentService !== null) {
                $doc = $this->documentService->attacher($programme, $fichier, 'programme_source');
                if (isset($doc->id)) {
                    $programme->update(['document_source_id' => $doc->id]);
                }
                if (isset($doc->fichier_path)) {
                    $cheminAbsolu = Storage::disk('documents')->path($doc->fichier_path);
                }
            }

            return [$programme, $cheminAbsolu];
        });

        if (empty($cheminAbsolu) || ! file_exists($cheminAbsolu)) {
            $cheminFichier = $fichier->getRealPath() ?: $fichier->store('programmes_temp');
            $cheminAbsolu = realpath($cheminFichier) ?: $cheminFichier;
        }

        ProcessProgrammeDocumentJob::dispatch($programme->id, $cheminAbsolu, $cycle);

        return $programme;
    }

    private function verifierAffectationOffrePourActeur(int $enseignantId, OffrePedagogique $offre, int $anneeScolaireId): void
    {
        if ($this->estProfilAdministratif()) {
            return;
        }

        $utilisateur = Auth::user();
        $enseignantConnecteId = $utilisateur !== null && $this->enseignantService !== null
            ? $this->enseignantService->getEnseignantIdPourUtilisateur((int) $utilisateur->id)
            : null;

        $affecte = $enseignantConnecteId === $enseignantId
            && AffectationPedagogique::query()
                ->where('enseignant_id', $enseignantId)
                ->where('offre_pedagogique_id', $offre->id)
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->where('actif', true)
                ->exists();

        if (! $affecte) {
            throw new InvalidArgumentException("Vous n'êtes pas affecté à cette offre pédagogique.");
        }
    }

    private function estProfilAdministratif(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur !== null && (
            $utilisateur->hasAnyRole(['Fondateur', 'Admin', 'Administrateur', 'Super Admin', 'Directeur'])
            || $utilisateur->can('pedagogie.progression.superviser')
        );
    }

    public function validerParEnseignant(int $programmeId, array $elementsModifies = []): object
    {
        $programme = Programme::findOrFail($programmeId);

        DB::transaction(function () use ($programme, $elementsModifies) {
            if (! empty($elementsModifies)) {
                foreach ($elementsModifies as $elemData) {
                    if (isset($elemData['id'])) {
                        ProgrammeChapitre::where('id', $elemData['id'])
                            ->where('programme_id', $programme->id)
                            ->update(array_filter([
                                'titre' => $elemData['titre'] ?? null,
                                'volume_horaire_prevu' => $elemData['volume_horaire_prevu'] ?? null,
                                'objectif' => $elemData['objectif'] ?? null,
                            ]));
                    }
                }
            }

            $programme->update([
                'statut' => StatutProgrammeEnum::SOUMIS->value,
            ]);
        });

        $this->notifierDirecteursNiveau($programme->niveau_id, $programme);

        return $programme;
    }

    public function creerNouvelleVersion(int $programmeId): object
    {
        $programmeOriginal = Programme::with('chapitres.lecons')->findOrFail($programmeId);

        return DB::transaction(function () use ($programmeOriginal) {
            $nouvelleVersion = $programmeOriginal->replicate([
                'id', 'created_at', 'updated_at',
            ]);
            $nouvelleVersion->version = ($programmeOriginal->version ?? 1) + 1;
            $nouvelleVersion->programme_parent_id = $programmeOriginal->programme_parent_id ?? $programmeOriginal->id;
            $nouvelleVersion->statut = StatutProgrammeEnum::BROUILLON->value;
            $nouvelleVersion->save();

            foreach ($programmeOriginal->chapitres as $chapitre) {
                $nouveauChapitre = $chapitre->replicate(['id', 'programme_id', 'created_at', 'updated_at']);
                $nouveauChapitre->programme_id = $nouvelleVersion->id;
                $nouveauChapitre->save();

                foreach ($chapitre->lecons as $lecon) {
                    $nouvelleLecon = $lecon->replicate(['id', 'programme_chapitre_id', 'created_at', 'updated_at']);
                    $nouvelleLecon->programme_chapitre_id = $nouveauChapitre->id;
                    $nouvelleLecon->save();
                }
            }

            return $nouvelleVersion;
        });
    }

    public function archiverProgramme(int $programmeId): void
    {
        $programme = Programme::findOrFail($programmeId);
        $programme->update(['statut' => StatutProgrammeEnum::ARCHIVE->value]);
    }

    public function importerChapitresDepuisExcel(UploadedFile $fichierExcel): array
    {
        $extension = strtolower($fichierExcel->getClientOriginalExtension());
        $chapitres = [];

        if (class_exists(IOFactory::class)) {
            try {
                $spreadsheet = IOFactory::load($fichierExcel->getRealPath());
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                $firstRow = reset($rows);
                if (is_array($firstRow) && (
                    str_contains(strtolower((string) ($firstRow[0] ?? '')), 'titre') ||
                    str_contains(strtolower((string) ($firstRow[0] ?? '')), 'ordre') ||
                    str_contains(strtolower((string) ($firstRow[1] ?? '')), 'titre')
                )) {
                    array_shift($rows);
                }

                $ordreIndex = 1;
                foreach ($rows as $row) {
                    if (empty($row) || (empty($row[0]) && empty($row[1]))) {
                        continue;
                    }

                    $titre = is_numeric($row[0]) ? (string) ($row[1] ?? '') : (string) ($row[0] ?? '');
                    $ordre = is_numeric($row[0]) ? (int) $row[0] : (is_numeric($row[1] ?? null) ? (int) $row[1] : $ordreIndex);
                    $objectifs = (string) ($row[2] ?? '');
                    $periodeId = is_numeric($row[3] ?? null) ? (int) $row[3] : null;

                    if ($titre !== '') {
                        $chapitres[] = [
                            'titre' => trim($titre),
                            'ordre' => $ordre,
                            'objectifs_pedagogiques' => trim($objectifs) !== '' ? trim($objectifs) : null,
                            'periode_prevue_id' => $periodeId,
                        ];
                        $ordreIndex++;
                    }
                }
            } catch (\Throwable) {
                // Fallback below
            }
        }

        if (empty($chapitres) && in_array($extension, ['csv', 'txt'])) {
            if (($handle = fopen($fichierExcel->getRealPath(), 'r')) !== false) {
                $ordreIndex = 1;
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    if (empty($row) || (empty($row[0]) && empty($row[1]))) {
                        continue;
                    }
                    $titre = is_numeric($row[0]) ? (string) ($row[1] ?? '') : (string) ($row[0] ?? '');
                    if ($titre !== '' && strtolower($titre) !== 'titre') {
                        $chapitres[] = [
                            'titre' => trim($titre),
                            'ordre' => $ordreIndex,
                            'objectifs_pedagogiques' => isset($row[2]) && trim((string) $row[2]) !== '' ? trim((string) $row[2]) : null,
                            'periode_prevue_id' => is_numeric($row[3] ?? null) ? (int) $row[3] : null,
                        ];
                        $ordreIndex++;
                    }
                }
                fclose($handle);
            }
        }

        return $chapitres;
    }

    public function validerProgramme(int $programmeId, int $validateurId): void
    {
        $programme = Programme::findOrFail($programmeId);
        if ($programme->analyse_statut !== null) {
            app(ProgramStructureService::class)->enregistrer($programme, app(ProgramStructureService::class)->proposition($programme), true);

            return;
        }
        $acteur = Auth::user();
        if ($acteur !== null && ! $acteur->hasRole('Fondateur')) {
            throw new \DomainException('Seul le Fondateur peut valider un programme pédagogique.');
        }
        $programme = Programme::findOrFail($programmeId);
        $programme->update([
            'statut' => 'valide',
            'valide_par' => $validateurId,
            'motif_rejet' => null,
        ]);

        if ($this->auditService !== null) {
            $this->auditService->enregistrer($programme, "Validation du programme enseignant #{$programmeId}");
        }
    }

    public function rejeterProgramme(int $programmeId, string $motif): void
    {
        $acteur = Auth::user();
        if ($acteur !== null && ! $acteur->hasRole('Fondateur')) {
            throw new \DomainException('Seul le Fondateur peut rejeter un programme pédagogique.');
        }
        if (trim($motif) === '') {
            throw new InvalidArgumentException('Un motif de rejet est obligatoire.');
        }

        $programme = Programme::findOrFail($programmeId);
        $programme->update([
            'statut' => 'rejete',
            'motif_rejet' => $motif,
        ]);

        if ($this->auditService !== null) {
            $this->auditService->enregistrerAvecMotif($programme, "Rejet du programme enseignant #{$programmeId}", $motif);
        }

        if ($programme->enseignant_id) {
            $enseignantUser = User::find($programme->enseignant_id);
            if ($enseignantUser !== null && class_exists(Notification::class)) {
                Notification::make()
                    ->title('Programme enseignant rejeté')
                    ->body("Motif du rejet : {$motif}")
                    ->danger()
                    ->sendToDatabase($enseignantUser);
            }
        }
    }

    public function getChapitresPrevus(int $matiereId, int $classeId, int $anneeScolaireId, ?int $enseignantId = null): Collection
    {
        $statutsAcceptes = ['valide', 'publie', 'soumis'];

        $offre = OffrePedagogique::query()
            ->where('matiere_id', $matiereId)
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->first();

        if ($offre !== null) {
            $programmeOffre = Programme::query()
                ->whereIn('statut', $statutsAcceptes)
                ->where(fn ($query) => $query
                    ->where('offre_pedagogique_id', $offre->id)
                    ->orWhere('id', $offre->programme_id))
                ->orderByDesc('version')->orderByDesc('id')
                ->first();

            if ($programmeOffre !== null) {
                return ProgrammeChapitre::where('programme_id', $programmeOffre->id)
                    ->orderBy('ordre')
                    ->get();
            }
        }

        $programmeEnseignant = Programme::where('matiere_id', $matiereId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('source', 'enseignant')
            ->whereIn('statut', $statutsAcceptes)
            ->where(function ($q) use ($classeId) {
                $q->where('classe_id', $classeId)->orWhereNull('classe_id');
            })
            ->when($enseignantId, fn ($q) => $q->where('enseignant_id', $enseignantId))
            ->orderByDesc('id')->first();

        if ($programmeEnseignant !== null) {
            return ProgrammeChapitre::where('programme_id', $programmeEnseignant->id)
                ->orderBy('ordre', 'asc')
                ->get();
        }

        $programmeOfficiel = Programme::where('matiere_id', $matiereId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('source', 'officiel')
            ->whereIn('statut', ['valide', 'publie', 'soumis'])
            ->where(function ($query) use ($classeId) {
                $query->where('classe_id', $classeId)
                    ->orWhereNull('classe_id');
            })
            ->first();

        if ($programmeOfficiel !== null) {
            return ProgrammeChapitre::where('programme_id', $programmeOfficiel->id)
                ->orderBy('ordre', 'asc')
                ->get();
        }

        return collect();
    }

    public function getProchaineNotionAEffectuer(
        int $matiereId,
        int $classeId,
        int $anneeScolaireId,
        ?int $enseignantId = null
    ): array {
        $statutsAcceptes = ['valide', 'publie', 'soumis'];

        $offre = OffrePedagogique::query()
            ->where('matiere_id', $matiereId)
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->first();

        $programme = $offre === null ? null : Programme::with(['chapitres.lecons'])
            ->whereIn('statut', $statutsAcceptes)
            ->where(fn ($query) => $query
                ->where('offre_pedagogique_id', $offre->id)
                ->orWhere('id', $offre->programme_id))
            ->orderByDesc('version')->orderByDesc('id')
            ->first();

        $programme ??= Programme::with(['chapitres.lecons'])
            ->where('matiere_id', $matiereId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('source', 'enseignant')
            ->whereIn('statut', $statutsAcceptes)
            ->where(function ($q) use ($classeId) {
                $q->where('classe_id', $classeId)->orWhereNull('classe_id');
            })
            ->when($enseignantId, fn ($q) => $q->where('enseignant_id', $enseignantId))
            ->orderByRaw("CASE WHEN statut = 'valide' THEN 1 WHEN statut = 'publie' THEN 2 WHEN statut = 'soumis' THEN 3 ELSE 4 END")
            ->orderByDesc('version')
            ->first();

        if ($programme === null) {
            $programme = Programme::with(['chapitres.lecons'])
                ->where('matiere_id', $matiereId)
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->where('source', 'officiel')
                ->whereIn('statut', ['valide', 'publie'])
                ->where(function ($query) use ($classeId) {
                    $query->where('classe_id', $classeId)->orWhereNull('classe_id');
                })
                ->orderByDesc('version')
                ->first();
        }

        if ($programme === null) {
            return [
                'programme' => null,
                'chapitre' => null,
                'lecon' => null,
                'titre_suggere' => null,
                'objectifs_suggeres' => null,
                'duree_minutes_suggeree' => 55,
                'chapitres_options' => [],
                'lecons_options' => [],
                'total_chapitres' => 0,
                'total_lecons' => 0,
                'lecons_terminees_count' => 0,
            ];
        }

        $chapitres = $programme->chapitres->sortBy('ordre')->values();
        $totalChapitres = $chapitres->count();
        $allLecons = $chapitres->flatMap->lecons;
        $totalLecons = $allLecons->count();

        // Récupérer les progressions déjà renseignées pour cette classe/matière
        $chapitreIds = $chapitres->pluck('id')->all();
        $progressionsPassees = Progression::query()
            ->whereIn('chapitre_id', $chapitreIds)
            ->whereHas('seance.emploiDuTemps', fn ($query) => $query
                ->where('classe_id', $classeId)
                ->where('matiere_id', $matiereId)
                ->where('annee_scolaire_id', $anneeScolaireId))
            ->get();

        $chapitresTraitesIds = $progressionsPassees->where('chapitre_termine', true)->pluck('chapitre_id')->unique()->all();
        $leconsTermineesIds = $progressionsPassees->where('statut_element', 'terminee')->pluck('lecon_id')->filter()->unique()->all();

        // Trouver le premier chapitre actif (non terminé)
        $chapitreActif = $chapitres->first(function ($c) use ($chapitresTraitesIds, $leconsTermineesIds) {
            if (in_array($c->id, $chapitresTraitesIds, true)) {
                return false;
            }
            if ($c->lecons->isNotEmpty()) {
                return $c->lecons->contains(fn ($l) => ! in_array($l->id, $leconsTermineesIds, true));
            }

            return ! in_array($c->id, $chapitresTraitesIds, true);
        }) ?: $chapitres->first();

        // Trouver la prochaine leçon dans ce chapitre
        $prochaineLecon = null;
        if ($chapitreActif && $chapitreActif->lecons->isNotEmpty()) {
            $prochaineLecon = $chapitreActif->lecons
                ->sortBy('ordre')
                ->first(fn ($l) => ! in_array($l->id, $leconsTermineesIds, true));
            if ($prochaineLecon === null) {
                $prochaineLecon = $chapitreActif->lecons->sortBy('ordre')->first();
            }
        }

        // Suggestions de contenu et durée
        $titreSuggere = null;
        $objectifsSuggeres = null;
        $dureeMinutes = 55;

        if ($prochaineLecon) {
            $titreSuggere = $prochaineLecon->titre;
            $objectifsSuggeres = $prochaineLecon->objectifs ?: ($chapitreActif?->objectif ?? $chapitreActif?->objectifs_pedagogiques);
            if ($prochaineLecon->volume_horaire_prevu && $prochaineLecon->volume_horaire_prevu > 0) {
                $dureeMinutes = (int) round($prochaineLecon->volume_horaire_prevu * 60);
            }
        } elseif ($chapitreActif) {
            $titreSuggere = $chapitreActif->titre;
            $objectifsSuggeres = $chapitreActif->objectif ?? $chapitreActif->objectifs_pedagogiques ?? $chapitreActif->contenu;
            if ($chapitreActif->volume_horaire_prevu && $chapitreActif->volume_horaire_prevu > 0) {
                $dureeMinutes = (int) round($chapitreActif->volume_horaire_prevu * 60);
            }
        }

        // Options pour les sélecteurs
        $chapitresOptions = $chapitres->mapWithKeys(fn ($c) => [
            $c->id => "{$c->titre}".($c->volume_horaire_prevu ? " ({$c->volume_horaire_prevu}h)" : ''),
        ])->all();

        $leconsOptions = [];
        if ($chapitreActif && $chapitreActif->lecons->isNotEmpty()) {
            $leconsOptions = $chapitreActif->lecons->sortBy('ordre')->mapWithKeys(fn ($l) => [
                $l->id => "{$l->titre}".($l->volume_horaire_prevu ? " ({$l->volume_horaire_prevu}h)" : ''),
            ])->all();
        }

        return [
            'programme' => $programme,
            'chapitre' => $chapitreActif,
            'lecon' => $prochaineLecon,
            'titre_suggere' => $titreSuggere,
            'objectifs_suggeres' => $objectifsSuggeres,
            'duree_minutes_suggeree' => $dureeMinutes,
            'chapitres_options' => $chapitresOptions,
            'lecons_options' => $leconsOptions,
            'total_chapitres' => $totalChapitres,
            'total_lecons' => $totalLecons,
            'lecons_terminees_count' => count($leconsTermineesIds),
        ];
    }

    public function ajouterLecon(
        int $chapitreId,
        string $titre,
        ?string $objectifs = null,
        float $dureeHeures = 1.0,
        int $ordre = 1
    ): object {
        ProgrammeChapitre::findOrFail($chapitreId);

        return ProgrammeLecon::create([
            'programme_chapitre_id' => $chapitreId,
            'titre' => $titre,
            'objectifs' => $objectifs,
            'volume_horaire_prevu' => $dureeHeures,
            'ordre' => $ordre,
            'statut' => 'a_faire',
        ]);
    }

    public function getLeconsChapitre(int $chapitreId): Collection
    {
        return ProgrammeLecon::where('programme_chapitre_id', $chapitreId)
            ->orderBy('ordre', 'asc')
            ->get();
    }

    private function notifierDirecteursNiveau(int $niveauId, Programme $programme): void
    {
        try {
            $directeurs = User::role('Fondateur')
                ->where(function ($query) use ($niveauId) {
                    $query->where('niveau_id', $niveauId)
                        ->orWhereNull('niveau_id');
                })
                ->get();
        } catch (\Throwable) {
            $directeurs = collect();
        }

        foreach ($directeurs as $directeur) {
            if (class_exists(Notification::class)) {
                Notification::make()
                    ->title('Programme enseignant en attente de votre validation')
                    ->body("Un programme enseignant (#{$programme->id}) a été soumis pour la classe #{$programme->classe_id}.")
                    ->info()
                    ->sendToDatabase($directeur);
            }
        }
    }

    public function lancerAnalyseIntelligente(int $programmeId, ?int $userId = null): object
    {
        $programme = Programme::findOrFail($programmeId);
        $programme->update([
            'statut' => StatutProgrammeEnum::ANALYSE_EN_COURS->value,
            'erreur_analyse' => null,
        ]);

        try {
            if (config('ai.sync', true) || app()->environment('local', 'testing') || config('queue.default') === 'sync') {
                AnalyzeProgrammeJob::dispatchSync($programme->id, null, $userId);
            } else {
                AnalyzeProgrammeJob::dispatch($programme->id, null, $userId);
            }
        } catch (\Throwable) {
            // L'erreur est consignée dans le job et enregistrée dans le programme et programme_analyses
        }

        return $programme->fresh();
    }
}
