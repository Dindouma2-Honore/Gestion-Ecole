<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Exceptions\EcritureAnneeNonAutoriseeException;
use App\Modules\Pedagogie\Exceptions\MatiereIntrouvableException;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\RH\Contracts\EnseignantServiceInterface;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

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
        $programme->update(['statut' => 'publie']);

        return $programme->toArray();
    }

    public function soumettreProgramme(
        int $enseignantId,
        int $matiereId,
        int $classeId,
        int $anneeScolaireId,
        UploadedFile $fichierSource,
        array $chapitres
    ): object {
        if ($this->enseignantService !== null && ! $this->enseignantService->estAffecteA($enseignantId, $matiereId, $classeId)) {
            throw new InvalidArgumentException("L'enseignant [{$enseignantId}] n'est pas affecté à la matière [{$matiereId}] et classe [{$classeId}].");
        }

        $niveauId = $this->classeService !== null ? $this->classeService->getNiveauId($classeId) : 1;

        $salleId = EmploiDuTemps::where('enseignant_id', $enseignantId)
            ->where('matiere_id', $matiereId)
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->value('salle_id');

        $programme = Programme::create([
            'matiere_id' => $matiereId,
            'niveau_id' => $niveauId,
            'classe_id' => $classeId,
            'annee_scolaire_id' => $anneeScolaireId,
            'source' => 'enseignant',
            'enseignant_id' => $enseignantId,
            'salle_id' => $salleId,
            'titre' => 'Programme Enseignant - Matière #' . $matiereId . ' Classe #' . $classeId,
            'statut' => 'soumis',
        ]);

        if ($this->documentService !== null) {
            $doc = $this->documentService->attacher($programme, $fichierSource, 'programme_source');
            if (isset($doc->id)) {
                $programme->update(['document_source_id' => $doc->id]);
            }
        }

        foreach ($chapitres as $index => $chap) {
            ProgrammeChapitre::create([
                'programme_id' => $programme->id,
                'titre' => (string) ($chap['titre'] ?? ('Chapitre ' . ($index + 1))),
                'ordre' => (int) ($chap['ordre'] ?? ($index + 1)),
                'objectifs_pedagogiques' => isset($chap['objectifs_pedagogiques']) ? (string) $chap['objectifs_pedagogiques'] : null,
                'periode_prevue_id' => isset($chap['periode_prevue_id']) && is_numeric($chap['periode_prevue_id']) ? (int) $chap['periode_prevue_id'] : null,
            ]);
        }

        $this->notifierDirecteursNiveau($niveauId, $programme);

        return $programme;
    }

    public function importerChapitresDepuisExcel(UploadedFile $fichierExcel): array
    {
        $extension = strtolower($fichierExcel->getClientOriginalExtension());
        $chapitres = [];

        if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fichierExcel->getRealPath());
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
            if ($enseignantUser !== null && class_exists(\Filament\Notifications\Notification::class)) {
                \Filament\Notifications\Notification::make()
                    ->title('Programme enseignant rejeté')
                    ->body("Motif du rejet : {$motif}")
                    ->danger()
                    ->sendToDatabase($enseignantUser);
            }
        }
    }

    public function getChapitresPrevus(int $matiereId, int $classeId, int $anneeScolaireId, ?int $enseignantId = null): Collection
    {
        $programmeEnseignant = Programme::where('matiere_id', $matiereId)
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('source', 'enseignant')
            ->where('statut', 'valide')
            ->when($enseignantId, fn ($q) => $q->where('enseignant_id', $enseignantId))
            ->first();

        if ($programmeEnseignant !== null) {
            return ProgrammeChapitre::where('programme_id', $programmeEnseignant->id)
                ->orderBy('ordre', 'asc')
                ->get();
        }

        $programmeOfficiel = Programme::where('matiere_id', $matiereId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('source', 'officiel')
            ->whereIn('statut', ['valide', 'publie'])
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

    private function notifierDirecteursNiveau(int $niveauId, Programme $programme): void
    {
        $directeurs = User::role(['Directeur', 'Fondateur'])
            ->where(function ($query) use ($niveauId) {
                $query->where('niveau_id', $niveauId)
                    ->orWhereNull('niveau_id');
            })
            ->get();

        foreach ($directeurs as $directeur) {
            if (class_exists(\Filament\Notifications\Notification::class)) {
                \Filament\Notifications\Notification::make()
                    ->title('Nouveau programme enseignant soumis')
                    ->body("Un programme enseignant (#{$programme->id}) a été soumis pour la classe #{$programme->classe_id}.")
                    ->info()
                    ->sendToDatabase($directeur);
            }
        }
    }
}