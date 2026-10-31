<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\EvaluationServiceInterface;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\RH\Contracts\EnseignantServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EvaluationService implements EvaluationServiceInterface
{
    public function __construct(
        private readonly ?DocumentServiceContract $documentService = null,
        private readonly ?AuditServiceContract $auditService = null,
        private readonly ?AnneeScolaireServiceContract $anneeScolaireService = null,
        private readonly ?EnseignantServiceInterface $enseignantService = null,
    ) {}

    public function proposerSujet(
        int $enseignantId,
        int $matiereId,
        int $classeId,
        string $titre,
        string $dateEvaluation,
        float $bareme,
        ?UploadedFile $sujet = null,
        ?int $anneeScolaireId = null,
        ?int $periodeId = null,
        string $typeEvaluation = 'devoir',
        float $coefficient = 1.0,
        string $modeEvaluation = 'note'
    ): object {
        if ($bareme <= 0) {
            throw new InvalidArgumentException('Le barème doit être strictement positif.');
        }

        if ($coefficient <= 0) {
            throw new InvalidArgumentException('Le coefficient doit être strictement positif.');
        }

        $anneeId = $anneeScolaireId;
        if ($anneeId === null && $this->anneeScolaireService !== null) {
            try {
                $anneeId = $this->anneeScolaireService->getAnneeCouranteId();
            } catch (\Throwable) {
                $anneeId = null;
            }
        }

        if ($anneeId !== null && $this->anneeScolaireService !== null) {
            $user = Auth::user() ?? User::find($enseignantId);
            if ($user !== null && ! $this->anneeScolaireService->ecritureAutorisee($anneeId, $user)) {
                throw new InvalidArgumentException("L'année scolaire n'est pas ouverte à l'écriture.");
            }

            if ($periodeId !== null) {
                $periodes = $this->anneeScolaireService->getPeriodes($anneeId);
                if (! empty($periodes)) {
                    $periodeExiste = collect($periodes)->contains(fn ($p) => (int) (is_array($p) ? $p['id'] : $p->id) === $periodeId);
                    if (! $periodeExiste) {
                        throw new InvalidArgumentException("La période sélectionnée ne correspond pas à l'année scolaire active.");
                    }
                }
            }
        }

        // Vérification de l'affectation de l'enseignant (sauf pour profil de direction/admin)
        $estAdmin = false;
        $user = Auth::user();
        if ($user !== null && method_exists($user, 'hasAnyRole')) {
            $estAdmin = $user->hasAnyRole(['Super Admin', 'Admin', 'Direction', 'Directeur', 'Directrice', 'Fondateur', 'Coordinateur Pédagogique']);
        }

        $targetEnseignantId = $enseignantId;
        if ($this->enseignantService !== null) {
            $fromUser = $this->enseignantService->getEnseignantIdPourUtilisateur($enseignantId);
            if ($fromUser !== null) {
                $targetEnseignantId = $fromUser;
            }
        }

        if (! $estAdmin) {
            $hasAnyAffectations = AffectationPedagogique::query()->exists();
            if ($hasAnyAffectations) {
                $affecte = AffectationPedagogique::query()
                    ->where(function ($q) use ($targetEnseignantId, $enseignantId): void {
                        $q->where('enseignant_id', $targetEnseignantId)
                            ->orWhere('enseignant_id', $enseignantId);
                    })
                    ->where('classe_id', $classeId)
                    ->where('actif', true)
                    ->where(function ($q) use ($matiereId): void {
                        $q->whereNull('matiere_id')
                            ->orWhere('matiere_id', $matiereId)
                            ->orWhereHas('matieresSpecialisees', fn ($sq) => $sq->where('matieres.id', $matiereId));
                    })
                    ->exists();

                if (! $affecte) {
                    throw new InvalidArgumentException("L'enseignant n'est pas affecté à cette classe et matière.");
                }
            }
        }

        return DB::transaction(function () use (
            $enseignantId,
            $targetEnseignantId,
            $matiereId,
            $classeId,
            $titre,
            $dateEvaluation,
            $bareme,
            $sujet,
            $anneeId,
            $periodeId,
            $typeEvaluation,
            $coefficient,
            $modeEvaluation
        ): Evaluation {
            $evaluation = Evaluation::create([
                'titre' => $titre,
                'matiere_id' => $matiereId,
                'classe_id' => $classeId,
                'annee_scolaire_id' => $anneeId,
                'periode_id' => $periodeId,
                'enseignant_id' => $targetEnseignantId,
                'type_evaluation' => $typeEvaluation,
                'mode_evaluation' => $modeEvaluation,
                'date_evaluation' => $dateEvaluation,
                'bareme' => $bareme,
                'coefficient_evaluation' => $coefficient,
                'created_by' => Auth::id() ?? $enseignantId,
                'statut' => 'soumis',
            ]);

            if ($sujet !== null && $this->documentService !== null) {
                $doc = $this->documentService->attacher($evaluation, $sujet, 'sujet_examen');
                if (isset($doc->id)) {
                    $evaluation->update(['document_sujet_id' => $doc->id]);
                }
            }

            $this->notifierDirecteurs($evaluation);

            return $evaluation;
        });
    }

    public function validerSujet(int $evaluationId, int $validateurId): void
    {
        $evaluation = Evaluation::findOrFail($evaluationId);

        $validateur = User::find($validateurId);
        if ($validateur !== null && method_exists($validateur, 'roles') && $validateur->roles()->exists()) {
            $isAutorise = $validateur->hasAnyRole([
                'Directeur',
                'Directrice',
                'Fondateur',
                'Super Admin',
                'Admin',
                'Direction',
                'Coordinateur Pédagogique',
            ]) || ($validateur->can('valider', $evaluation));

            if (! $isAutorise) {
                throw new AuthorizationException(
                    "L'utilisateur n'a pas l'autorité requise (Directeur/Fondateur) pour valider une évaluation."
                );
            }
        }

        $evaluation->update([
            'statut' => 'valide',
            'valide_par' => $validateurId,
            'motif_rejet' => null,
        ]);

        if ($this->auditService !== null) {
            $this->auditService->enregistrer($evaluation, "Validation du sujet d'examen #{$evaluationId}");
        }
    }

    public function rejeterSujet(int $evaluationId, string $motif, ?int $validateurId = null): void
    {
        if (trim($motif) === '') {
            throw new InvalidArgumentException('Un motif de rejet est obligatoire.');
        }

        $evaluation = Evaluation::findOrFail($evaluationId);

        if ($validateurId !== null) {
            $validateur = User::find($validateurId);
            if ($validateur !== null && method_exists($validateur, 'roles') && $validateur->roles()->exists()) {
                $isAutorise = $validateur->hasAnyRole([
                    'Directeur',
                    'Directrice',
                    'Fondateur',
                    'Super Admin',
                    'Admin',
                    'Direction',
                    'Coordinateur Pédagogique',
                ]) || ($validateur->can('rejeter', $evaluation));

                if (! $isAutorise) {
                    throw new AuthorizationException(
                        "L'utilisateur n'a pas l'autorité requise pour rejeter une évaluation."
                    );
                }
            }
        }

        $evaluation->update([
            'statut' => 'rejete',
            'motif_rejet' => $motif,
            'valide_par' => $validateurId,
        ]);

        if ($this->auditService !== null) {
            $this->auditService->enregistrerAvecMotif($evaluation, "Rejet du sujet d'examen #{$evaluationId}", $motif);
        }

        if ($evaluation->created_by) {
            $enseignantUser = User::find($evaluation->created_by);
            if ($enseignantUser !== null && class_exists(Notification::class)) {
                Notification::make()
                    ->title("Sujet d'examen rejeté")
                    ->body("Motif du rejet : {$motif}")
                    ->danger()
                    ->sendToDatabase($enseignantUser);
            }
        }
    }

    private function notifierDirecteurs(Evaluation $evaluation): void
    {
        if (! class_exists(Notification::class)) {
            return;
        }

        $directeurs = User::role(['Directeur', 'Fondateur'])->get();

        foreach ($directeurs as $directeur) {
            Notification::make()
                ->title("Nouveau sujet d'examen à valider")
                ->body("Un sujet d'examen (#{$evaluation->id} - {$evaluation->titre}) a été soumis pour la classe #{$evaluation->classe_id}.")
                ->info()
                ->sendToDatabase($directeur);
        }
    }
}
