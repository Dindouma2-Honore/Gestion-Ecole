<?php

namespace App\Modules\Pedagogie\Jobs;

use App\Modules\Pedagogie\Contracts\DocumentExtractor;
use App\Modules\Pedagogie\Contracts\ProgramDocumentAnalyzer;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Services\ProgramStructureService;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class AnalyseProgramme implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $programmeId) {}

    public function handle(DocumentExtractor $extractor, ProgramDocumentAnalyzer $analyzer, DocumentServiceContract $documents): void
    {
        if (! Programme::whereKey($this->programmeId)->where('analyse_statut', 'analyse_en_attente')->update(['analyse_statut' => 'analyse_en_cours'])) {
            return;
        }
        try {
            $p = Programme::findOrFail($this->programmeId);
            $source = $documents->sourceInterne((int) $p->document_source_id);
            $elements = $analyzer->analyser($extractor->extraire($source['path'], $source['extension']));
            DB::transaction(function () use ($elements): void {
                $p = Programme::lockForUpdate()->findOrFail($this->programmeId);
                if ($p->analyse_statut !== 'analyse_en_cours') {
                    return;
                }
                app(ProgramStructureService::class)->remplacer($p, $elements);
                $p->update(['analyse_statut' => 'a_valider', 'analyse_erreur' => null]);
            });
        } catch (\Throwable $e) {
            $this->failed($e);
            report($e);
        }
    }

    public function failed(?\Throwable $e): void
    {
        Programme::whereKey($this->programmeId)->whereIn('analyse_statut', ['analyse_en_attente', 'analyse_en_cours'])->update(['analyse_statut' => 'echec_analyse', 'analyse_erreur' => mb_substr($e?->getMessage() ?? 'Échec du worker', 0, 4000)]);
    }
}
