<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\NoteServiceInterface;
use App\Modules\Pedagogie\Exceptions\DeblocageNoteInterditException;
use App\Modules\Pedagogie\Exceptions\ExamenNonEncoreEffectueException;
use App\Modules\Pedagogie\Exceptions\NoteInvalideException;
use App\Modules\Pedagogie\Exceptions\NoteModificationVerrouilleeException;
use App\Modules\Pedagogie\Exceptions\SujetExamenNonValideException;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Pedagogie\Models\Note;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NoteService implements NoteServiceInterface
{
    public function __construct(
        private readonly ParametrageServiceContract $parametres,
        private readonly AuditServiceContract $audit,
        private readonly ?DocumentServiceContract $documentService = null,
        // TODO: décommenter une fois le vrai contrat de notification confirmé
        // côté Socle/Communication. Ce contrat enverra la notification WhatsApp
        // au parent à chaque note renseignée ; l'envoi effectif (intégration
        // WhatsApp) est implémenté par un autre développeur, ce Service se
        // contente de déclencher l'appel une fois la note enregistrée.
        // private readonly ?NotificationServiceContract $notification = null,
    ) {}

    public function enregistrer(int $evaluationId, int $eleveId, float $valeur, ?UploadedFile $copie = null): object
    {
        return DB::transaction(function () use ($evaluationId, $eleveId, $valeur, $copie): Note {
            $evaluation = Evaluation::findOrFail($evaluationId);

            if (! $evaluation->estValidee()) {
                throw SujetExamenNonValideException::pourEvaluation($evaluationId, (string) $evaluation->statut);
            }

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

            $note = Note::where('evaluation_id', $evaluationId)->where('eleve_id', $eleveId)->lockForUpdate()->first();
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
            if ($note->debloque_le === null && now()->lessThanOrEqualTo($this->dateVerrouillageNote($note))) {
                throw new NoteInvalideException('Cette note est encore modifiable et ne nécessite pas de déblocage.');
            }
            $note->update(['debloque_le' => now(), 'deblocage_consomme_le' => null, 'deblocage_autorise_par' => $acteur->getAuthIdentifier(), 'motif_deblocage' => trim($motif)]);
            $this->audit->enregistrerAvecMotif($note, 'Déblocage exceptionnel d’une note', $motif);
        });
    }

    private function peutModifierNote(Note $note): bool
    {
        return ! $note->verrouillee && ($note->debloque_le !== null
            ? $note->deblocage_consomme_le === null
            : now()->lessThanOrEqualTo($this->dateVerrouillageNote($note)));
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
