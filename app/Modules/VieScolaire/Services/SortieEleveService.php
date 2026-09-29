<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Services;

use App\Modules\Scolarite\Contracts\ParentTuteurServiceInterface;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\VieScolaire\Contracts\SortieEleveServiceInterface;
use App\Modules\VieScolaire\Exceptions\PersonneNonAutoriseeException;
use App\Modules\VieScolaire\Models\SortieEleve;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SortieEleveService implements SortieEleveServiceInterface
{
    public function __construct(
        private readonly ParentTuteurServiceInterface $parent,
        private readonly DocumentServiceContract $document,
        private readonly AuditServiceContract $audit,
    ) {}

    public function enregistrerSortieNormale(int $eleveId, int $parentId): object
    {
        // Vérification AVANT écriture — jamais l'inverse, c'est le module le
        // plus sensible en sécurité physique des enfants de tout le projet.
        $personnesAutorisees = collect($this->parent->getPersonnesAutoriseesRecuperer($eleveId));

        if (! $personnesAutorisees->contains('id', $parentId)) {
            throw new PersonneNonAutoriseeException($parentId, $eleveId);
        }

        return SortieEleve::create([
            'eleve_id' => $eleveId,
            'parent_id' => $parentId,
            'type' => 'normale',
            'heure_sortie' => now(),
            'remis_par' => Auth::id(),
        ]);
    }

    public function enregistrerSortieExceptionnelle(
        int $eleveId,
        string $nomPersonne,
        string $motif,
        ?UploadedFile $justificatif = null
    ): object {
        $sortie = SortieEleve::create([
            'eleve_id' => $eleveId,
            'personne_autorisee_nom' => $nomPersonne,
            'type' => 'exceptionnelle',
            'heure_sortie' => now(),
            'remis_par' => Auth::id(),
        ]);

        if ($justificatif) {
            // Justificatif attaché à la SortieEleve elle-même, pas à l'Eleve
            // (accès direct au Model Eleve interdit — module Scolarité).
            $document = $this->document->attacher($sortie, $justificatif, 'autorisation_sortie', 'interne');
            $sortie->update(['justificatif_document_id' => $document->id]);
        }

        $this->audit->enregistrerAvecMotif($sortie, "Sortie exceptionnelle de l'élève #{$eleveId}", $motif);

        return $sortie;
    }

    public function getHistoriqueSorties(int $eleveId): Collection
{
    return SortieEleve::where('eleve_id', $eleveId)
        ->orderByDesc('heure_sortie')
        ->orderByDesc('id')   // <-- départage à égalité de seconde
        ->get();
}
}
