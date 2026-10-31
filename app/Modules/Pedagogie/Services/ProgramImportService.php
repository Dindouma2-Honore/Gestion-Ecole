<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Jobs\AnalyseProgramme;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProgramImportService
{
    public function importer(int $anneeId, int $classeId, int $offreId, UploadedFile $fichier): Programme
    {
        $context = app(ProgrammeAccessService::class)->contexte($anneeId, $classeId, $offreId);
        Validator::make(['fichier' => $fichier], ['fichier' => 'required|file|max:5120|extensions:pdf,doc,docx'])->validate();
        $extension = strtolower($fichier->getClientOriginalExtension());
        $mimes = ['pdf' => ['application/pdf'], 'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip']];
        if (! in_array($fichier->getMimeType(), $mimes[$extension] ?? [], true)) {
            throw ValidationException::withMessages(['fichier' => 'Le contenu du fichier ne correspond pas au format annoncé.']);
        }
        $header = file_get_contents($fichier->getRealPath(), false, null, 0, 8);
        if (($extension === 'pdf' && ! str_starts_with($header, '%PDF-')) || ($extension === 'doc' && bin2hex($header) !== 'd0cf11e0a1b11ae1')) {
            throw ValidationException::withMessages(['fichier' => 'Signature du document invalide.']);
        }
        if ($extension === 'docx') {
            $zip = new \ZipArchive;
            if ($zip->open($fichier->getRealPath()) !== true) {
                throw ValidationException::withMessages(['fichier' => 'Document Word invalide.']);
            }
            try {
                $meta = $zip->statName('[Content_Types].xml');
                if (! $meta || $meta['size'] > 100000) {
                    throw ValidationException::withMessages(['fichier' => 'Archive Word invalide.']);
                }
                $content = $zip->getFromName('[Content_Types].xml');
                if ($content === false || ! str_contains($content, 'wordprocessingml.document.main+xml') || ! $zip->statName('word/document.xml') || $zip->statName('word/vbaProject.bin')) {
                    throw ValidationException::withMessages(['fichier' => 'Le fichier ne contient pas un document DOCX accepté.']);
                }
            } finally {
                $zip->close();
            }
        }

        return DB::transaction(function () use ($context, $fichier): Programme {
            app(OrganisationPedagogiqueContext::class)->lock($context['annee_scolaire_id']);
            $query = Programme::where('enseignant_id', $context['enseignant_id'])->where('offre_pedagogique_id', $context['offre_id'])->where('classe_id', $context['classe_id'])->where('annee_scolaire_id', $context['annee_scolaire_id']);
            $root = (clone $query)->orderBy('id')->first();
            $p = Programme::create(['matiere_id' => $context['matiere_id'], 'offre_pedagogique_id' => $context['offre_id'], 'niveau_id' => $context['niveau_id'], 'classe_id' => $context['classe_id'], 'annee_scolaire_id' => $context['annee_scolaire_id'], 'enseignant_id' => $context['enseignant_id'],
                'titre' => $context['label'], 'source' => 'enseignant', 'statut' => 'brouillon', 'analyse_statut' => 'analyse_en_attente', 'importe_le' => now(), 'origine_programme_id' => $root?->id, 'version' => (int) (clone $query)->max('version') + 1]);
            if (! $root) {
                $p->update(['origine_programme_id' => $p->id]);
            }
            app(AuditServiceContract::class)->enregistrer($p, 'Import d’une version de programme pédagogique');
            $doc = app(DocumentServiceContract::class)->attacher($p, $fichier, 'programme_source', 'restreint');
            $p->update(['document_source_id' => $doc->id]);
            AnalyseProgramme::dispatch($p->id)->onConnection('database')->onQueue('pedagogie')->afterCommit();

            return $p;
        });
    }

    public function relancer(Programme $p): void
    {
        app(ProgrammeAccessService::class)->modifier($p);
        abort_unless($p->analyse_statut === 'echec_analyse', 422);
        if (Programme::whereKey($p->id)->where('analyse_statut', 'echec_analyse')->update(['analyse_statut' => 'analyse_en_attente', 'analyse_erreur' => null])) {
            AnalyseProgramme::dispatch($p->id)->onConnection('database')->onQueue('pedagogie')->afterCommit();
        }
    }
}
