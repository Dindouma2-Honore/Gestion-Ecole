<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\NoteServiceInterface;
use App\Modules\Pedagogie\Exceptions\DeblocageNoteInterditException;
use App\Modules\Pedagogie\Exceptions\ExamenNonEncoreEffectueException;
use App\Modules\Pedagogie\Exceptions\NoteInvalideException;
use App\Modules\Pedagogie\Exceptions\NoteModificationVerrouilleeException;
use App\Modules\Pedagogie\Exceptions\SujetExamenNonValideException;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Pedagogie\Models\Note;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use DateTimeInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NoteService implements NoteServiceInterface
{
    public function __construct(
        private readonly ParametrageServiceContract $parametres,
        private readonly AuditServiceContract $audit,
        private readonly ?DocumentServiceContract $documentService = null,
        private readonly ?EleveServiceInterface $eleveService = null,
    ) {}

    public function enregistrer(int $evaluationId, int $eleveId, float $valeur, ?UploadedFile $copie = null): object
    {
        return DB::transaction(function () use ($evaluationId, $eleveId, $valeur, $copie): Note {
            $evaluation = Evaluation::findOrFail($evaluationId);

            if (! $evaluation->estValidee()) {
                throw SujetExamenNonValideException::pourEvaluation($evaluationId, (string) $evaluation->statut);
            }

            $note = Note::where('evaluation_id', $evaluationId)->where('eleve_id', $eleveId)->lockForUpdate()->first();
            $exceptionnelle = $note !== null && $note->debloque_le !== null && $note->deblocage_consomme_le === null;

            if ($evaluation->verrouillee && ! $exceptionnelle) {
                throw new NoteModificationVerrouilleeException('Cette évaluation est entièrement verrouillée.');
            }

            $this->verifierEleveOfficiel($evaluation, $eleveId);

            if ($evaluation->date_evaluation !== null && now()->lt($evaluation->date_evaluation)) {
                throw ExamenNonEncoreEffectueException::pourEvaluation(
                    $evaluationId,
                    $evaluation->date_evaluation->format('d/m/Y')
                );
            }

            if ($valeur < 0 || $valeur > $evaluation->bareme) {
                throw new NoteInvalideException("La note doit être comprise entre 0 et {$evaluation->bareme}.");
            }

            $documentCopieId = null;
            if ($copie !== null && $this->documentService !== null) {
                $document = $this->documentService->attacher($evaluation, $copie, 'copie_eleve');
                $documentCopieId = $document->id ?? null;
            }

            if (! $note) {
                $note = Note::create([
                    'evaluation_id' => $evaluationId,
                    'eleve_id' => $eleveId,
                    'valeur' => $valeur,
                    'absent' => false,
                    'saisie_par' => Auth::id(),
                    'document_copie_id' => $documentCopieId,
                    'premiere_saisie_at' => now(),
                ]);

                $this->notifierParent($note, $evaluation);

                return $note;
            }
            if (! $this->peutModifierNote($note)) {
                throw new NoteModificationVerrouilleeException('Cette note est verrouillée depuis le '.$this->dateVerrouillageNote($note)->format('d/m/Y H:i').'. Une autorisation du Fondateur est requise.');
            }

            $exceptionnelle = $note->debloque_le !== null && $note->deblocage_consomme_le === null;
            $note->valeur = $valeur;
            $note->absent = false;
            $note->saisie_par = Auth::id();
            if ($documentCopieId !== null) {
                $note->document_copie_id = $documentCopieId;
            }
            if ($exceptionnelle) {
                $note->deblocage_consomme_le = now();
            }
            $note->save();
            if ($exceptionnelle) {
                $this->audit->enregistrerAvecMotif($note, 'Correction exceptionnelle d’une note puis reverrouillage immédiat', (string) $note->motif_deblocage);
            }

            $this->notifierParent($note, $evaluation);

            return $note;
        });
    }

    public function enregistrerAbsence(int $evaluationId, int $eleveId): object
    {
        $evaluation = Evaluation::findOrFail($evaluationId);
        if (! $evaluation->estValidee()) {
            throw SujetExamenNonValideException::pourEvaluation($evaluationId, (string) $evaluation->statut);
        }

        if ($evaluation->verrouillee) {
            throw new NoteModificationVerrouilleeException('Cette évaluation est entièrement verrouillée.');
        }

        $this->verifierEleveOfficiel($evaluation, $eleveId);

        return Note::updateOrCreate(
            ['evaluation_id' => $evaluationId, 'eleve_id' => $eleveId],
            ['valeur' => null, 'absent' => true, 'saisie_par' => Auth::id(), 'premiere_saisie_at' => now()]
        );
    }

    public function peutModifier(int $noteId): bool
    {
        return $this->peutModifierNote(Note::findOrFail($noteId));
    }

    public function dateVerrouillage(int $noteId): DateTimeInterface
    {
        return $this->dateVerrouillageNote(Note::findOrFail($noteId));
    }

    public function debloquer(int $noteId, string $motif): void
    {
        $acteur = Auth::user();
        if (! $acteur || ! method_exists($acteur, 'hasRole') || ! $acteur->hasRole('Fondateur')) {
            throw new DeblocageNoteInterditException('Seul le Fondateur peut autoriser une correction exceptionnelle.');
        }
        if (trim($motif) === '') {
            throw new NoteInvalideException('Le motif du déblocage est obligatoire.');
        }

        DB::transaction(function () use ($noteId, $motif, $acteur): void {
            $note = Note::lockForUpdate()->findOrFail($noteId);
            $estVerrouillee = $note->verrouillee
                || (bool) $note->evaluation?->verrouillee
                || $note->statut === 'valide'
                || now()->greaterThan($this->dateVerrouillageNote($note));

            if (! $estVerrouillee && $note->debloque_le === null) {
                throw new NoteInvalideException('Cette note est encore modifiable et ne nécessite pas de déblocage.');
            }
            $note->update(['debloque_le' => now(), 'deblocage_consomme_le' => null, 'deblocage_autorise_par' => $acteur->getAuthIdentifier(), 'motif_deblocage' => trim($motif)]);
            $this->audit->enregistrerAvecMotif($note, 'Déblocage exceptionnel d’une note', $motif);
        });
    }

    public function enregistrerLot(int $evaluationId, array $notesEleves): array
    {
        return DB::transaction(function () use ($evaluationId, $notesEleves): array {
            $evaluation = Evaluation::findOrFail($evaluationId);

            if (! $evaluation->estValidee()) {
                throw SujetExamenNonValideException::pourEvaluation($evaluationId, (string) $evaluation->statut);
            }

            if ($evaluation->verrouillee) {
                throw new NoteModificationVerrouilleeException('Cette évaluation est entièrement verrouillée.');
            }

            $nbSaisies = 0;
            $nbAbsents = 0;
            $sommeNotes = 0.0;
            $nbNotesCalcul = 0;

            foreach ($notesEleves as $item) {
                $eleveId = (int) $item['eleve_id'];
                $this->verifierEleveOfficiel($evaluation, $eleveId);
                $statutPresence = $item['statut_presence'] ?? 'present';
                $valeur = isset($item['valeur']) && $item['valeur'] !== '' && $item['valeur'] !== null ? (float) $item['valeur'] : null;
                $competenceNiveau = $item['competence_niveau'] ?? null;
                $appreciation = $item['appreciation'] ?? null;

                $estAbsent = in_array($statutPresence, ['absent_justifie', 'absent_injustifie', 'absent', 'dispense'], true);

                if ($estAbsent) {
                    $nbAbsents++;
                    if ($statutPresence === 'absent_injustifie') {
                        $valeur = 0.0;
                        $sommeNotes += 0.0;
                        $nbNotesCalcul++;
                    } else {
                        $valeur = null;
                    }
                } elseif ($valeur !== null) {
                    if ($valeur < 0 || $valeur > $evaluation->bareme) {
                        throw new NoteInvalideException("La note de l'élève #{$eleveId} ({$valeur}) doit être comprise entre 0 et {$evaluation->bareme}.");
                    }
                    $sommeNotes += $valeur;
                    $nbNotesCalcul++;
                }

                $note = Note::where('evaluation_id', $evaluationId)->where('eleve_id', $eleveId)->first();

                if (! $note) {
                    Note::create([
                        'evaluation_id' => $evaluationId,
                        'eleve_id' => $eleveId,
                        'valeur' => $valeur,
                        'competence_niveau' => $competenceNiveau,
                        'appreciation' => $appreciation,
                        'absent' => $estAbsent,
                        'statut_presence' => $statutPresence,
                        'saisie_par' => Auth::id(),
                        'premiere_saisie_at' => now(),
                    ]);
                } else {
                    if (! $this->peutModifierNote($note)) {
                        throw new NoteModificationVerrouilleeException("La note de l'élève #{$eleveId} est verrouillée depuis le ".$this->dateVerrouillageNote($note)->format('d/m/Y H:i').'. Une autorisation du Fondateur est requise.');
                    }
                    $note->update([
                        'valeur' => $valeur,
                        'competence_niveau' => $competenceNiveau,
                        'appreciation' => $appreciation,
                        'absent' => $estAbsent,
                        'statut_presence' => $statutPresence,
                        'saisie_par' => Auth::id(),
                    ]);
                }

                $nbSaisies++;
            }

            $moyenne = $nbNotesCalcul > 0 ? round($sommeNotes / $nbNotesCalcul, 2) : null;

            return [
                'evaluation_id' => $evaluationId,
                'nb_saisies' => $nbSaisies,
                'nb_absents' => $nbAbsents,
                'moyenne' => $moyenne,
            ];
        });
    }

    public function soumettreNotes(int $evaluationId, ?int $soumetteurId = null): void
    {
        $evaluation = Evaluation::findOrFail($evaluationId);

        if (! $evaluation->estValidee()) {
            throw SujetExamenNonValideException::pourEvaluation($evaluationId, (string) $evaluation->statut);
        }

        if ($evaluation->verrouillee) {
            throw new NoteModificationVerrouilleeException('Cette évaluation est déjà verrouillée.');
        }

        $totalNotes = Note::where('evaluation_id', $evaluationId)->count();
        if ($totalNotes === 0) {
            throw new DomainException('Aucune note enregistrée à soumettre pour cette évaluation.');
        }

        if ($this->eleveService !== null) {
            $elevesOfficiels = $this->eleveService->getElevesParClasse((int) $evaluation->classe_id);
            if (! empty($elevesOfficiels)) {
                $idsOfficiels = collect($elevesOfficiels)->pluck('id')->map(fn ($id) => (int) $id)->all();
                $idsSaisis = Note::where('evaluation_id', $evaluationId)->pluck('eleve_id')->map(fn ($id) => (int) $id)->all();
                $manquants = array_diff($idsOfficiels, $idsSaisis);

                if (! empty($manquants)) {
                    $nbManquants = count($manquants);
                    throw new DomainException("Saisie incomplète : {$nbManquants} élève(s) de la classe n'ont pas encore été évalué(s).");
                }
            }
        }

        DB::transaction(function () use ($evaluationId, $soumetteurId, $evaluation): void {
            Note::where('evaluation_id', $evaluationId)->update([
                'statut' => 'soumis',
                'soumis_par' => $soumetteurId ?? Auth::id(),
                'soumis_le' => now(),
            ]);

            $this->audit->enregistrer($evaluation, "Soumission des notes de l'évaluation #{$evaluationId}");
        });
    }

    public function approuverNotes(int $evaluationId, int $approbateurId): void
    {
        $evaluation = Evaluation::findOrFail($evaluationId);

        $approbateur = User::find($approbateurId);
        if ($approbateur !== null && method_exists($approbateur, 'roles') && $approbateur->roles()->exists()) {
            $isFondateur = $approbateur->hasAnyRole(['Fondateur', 'Super Admin']) || ($approbateur->can('approuver', $evaluation));
            if (! $isFondateur) {
                throw new AuthorizationException(
                    'Seul le Fondateur (ou autorité équivalente) peut approuver et geler les notes.'
                );
            }
        }

        DB::transaction(function () use ($evaluation, $evaluationId, $approbateurId): void {
            Note::where('evaluation_id', $evaluationId)->update([
                'statut' => 'valide',
                'valide_par' => $approbateurId,
                'valide_le' => now(),
                'verrouillee' => true,
            ]);

            $evaluation->update(['verrouillee' => true]);

            $this->audit->enregistrer($evaluation, "Approbation par le Fondateur et gel des notes de l'évaluation #{$evaluationId}");
        });
    }

    public function verrouillerEvaluation(int $evaluationId): void
    {
        $evaluation = Evaluation::findOrFail($evaluationId);
        $evaluation->update(['verrouillee' => true]);
        $evaluation->notes()->update([
            'verrouillee' => true,
            'statut' => 'valide',
        ]);

        $this->audit->enregistrer($evaluation, "Verrouillage de l'évaluation #{$evaluationId} et de ses notes");
    }

    private function verifierEleveOfficiel(Evaluation $evaluation, int $eleveId): void
    {
        if ($this->eleveService !== null) {
            $elevesOfficiels = $this->eleveService->getElevesParClasse((int) $evaluation->classe_id);
            if (! empty($elevesOfficiels)) {
                $ids = collect($elevesOfficiels)->pluck('id')->map(fn ($id) => (int) $id)->all();
                if (! in_array($eleveId, $ids, true)) {
                    throw new InvalidArgumentException("L'élève #{$eleveId} n'appartient pas à la classe officielle de cette évaluation.");
                }
            }
        }
    }

    private function peutModifierNote(Note $note): bool
    {
        $exceptionnelle = $note->debloque_le !== null && $note->deblocage_consomme_le === null;
        if ($exceptionnelle) {
            return true;
        }

        if ($note->evaluation?->verrouillee) {
            return false;
        }

        if ($note->verrouillee) {
            return false;
        }

        if ($note->statut === 'valide') {
            return false;
        }

        return now()->lessThanOrEqualTo($this->dateVerrouillageNote($note));
    }

    private function dateVerrouillageNote(Note $note): DateTimeInterface
    {
        return $note->premiere_saisie_at->copy()->addDays(max(0, (int) $this->parametres->getParametre('delaiModificationNotesJours', 7)));
    }

    /**
     * Point d'ancrage pour la notification WhatsApp au parent concernant la
     * note de l'élève. L'envoi réel (choix du provider WhatsApp, gabarit du
     * message, gestion des échecs/retries) sera implémenté par un autre
     * développeur via le contrat de notification du module Socle/Communication
     * une fois celui-ci confirmé — voir TODO dans le constructeur.
     *
     * Volontairement no-op tant que le contrat n'existe pas, pour ne pas
     * bloquer la saisie des notes en attendant cette intégration.
     */
    private function notifierParent(Note $note, Evaluation $evaluation): void
    {
        // TODO: appeler $this->notification->envoyerAuxParents(
        //     eleveId: $note->eleve_id,
        //     canal: 'whatsapp',
        //     modele: 'note_renseignee',
        //     donnees: ['matiere_id' => $evaluation->matiere_id, 'titre_evaluation' => $evaluation->titre, 'note' => $note->valeur, 'bareme' => $evaluation->bareme],
        // );
    }
}
