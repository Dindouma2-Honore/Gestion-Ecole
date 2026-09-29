<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Services;

use App\Modules\Assiduite\Contracts\SortieEleveServiceContract;
use App\Modules\Assiduite\Exceptions\PersonneNonAutoriseeException;
use App\Modules\Assiduite\Models\SortieEleve;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SortieEleveService implements SortieEleveServiceContract
{
    public function __construct(
        private ?AuditServiceContract $audit = null,
        private ?DocumentServiceContract $document = null
    ) {}

    public function enregistrerSortieNormale(int $eleveId, int $parentId): object
    {
        $estAutorise = DB::table('eleve_parent')
            ->where('eleve_id', $eleveId)
            ->where('parent_id', $parentId)
            ->where('autorise_recuperation', true)
            ->exists();

        if (!$estAutorise) {
            throw new PersonneNonAutoriseeException($parentId, $eleveId);
        }

        return SortieEleve::create([
            'eleve_id' => $eleveId,
            'parent_id' => $parentId,
            'type' => 'normale',
            'heure_sortie' => now(),
            'remis_par' => Auth::id() ?? 1,
        ]);
    }

    public function enregistrerSortieExceptionnelle(int $eleveId, string $nomPersonne, string $motif, ?UploadedFile $justificatif = null): object
    {
        $documentId = null;

        if ($justificatif && $this->document) {
            $sortieTemp = new SortieEleve(['eleve_id' => $eleveId]);
            $doc = $this->document->attacher($sortieTemp, $justificatif, 'autorisation_sortie', 'interne');
            $documentId = data_get($doc, 'id');
        }

        $sortie = SortieEleve::create([
            'eleve_id' => $eleveId,
            'personne_autorisee_nom' => $nomPersonne,
            'type' => 'exceptionnelle',
            'heure_sortie' => now(),
            'justificatif_document_id' => $documentId,
            'remis_par' => Auth::id() ?? 1,
        ]);

        if ($this->audit) {
            $this->audit->enregistrerAvecMotif($sortie, "Sortie exceptionnelle de l'élève #{$eleveId}", $motif);
        }

        return $sortie;
    }

    public function getHistoriqueSorties(int $eleveId): Collection
    {
        return SortieEleve::where('eleve_id', $eleveId)->orderByDesc('heure_sortie')->get();
    }
}
