<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\OrganisationCycle;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class OrganisationCycleService
{
    public function __construct(private OrganisationPedagogiqueContext $context, private AuditServiceContract $audit) {}

    public function autorise(): bool
    {
        return $this->context->canManage('App\\Modules\\Pedagogie\\Filament\\Pages\\Enseignements')
            && (auth()->user()->hasRole('Fondateur') || auth()->user()->can('gerer_parametrage'));
    }

    public function enregistrer(array $rows): void
    {
        abort_unless($this->autorise(), 403);
        Validator::make(['cycles' => $rows], ['cycles' => 'required|array', 'cycles.*.cycle_id' => 'required|integer|distinct', 'cycles.*.mode' => 'required|in:classe,matiere'])->validate();
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $data) {
                $cycleId = (int) $data['cycle_id'];
                if (! isset($this->context->cycles()[$cycleId])) {
                    throw ValidationException::withMessages(['cycles' => 'Cycle inconnu.']);
                }
                OrganisationCycle::firstOrCreate(['cycle_id' => $cycleId]);
                $record = OrganisationCycle::where('cycle_id', $cycleId)->lockForUpdate()->firstOrFail();
                if ($record->mode !== $data['mode']) {
                    $levelIds = collect($this->context->niveaux())->where('cycle_id', $cycleId)->keys()->all();
                    foreach (array_keys($this->context->annees()) as $year) {
                        $classes = collect($this->context->classes($year))->whereIn('niveau_id', $levelIds)->keys();
                        if (AffectationPedagogique::whereIn('classe_id', $classes)->exists()) {
                            throw ValidationException::withMessages(['cycles' => 'Ce cycle possède des affectations historiques. Son mode ne peut plus être changé directement.']);
                        }
                    }
                }
                $record->update(['mode' => $data['mode']]);
                $this->audit->enregistrer($record, 'Configuration du mode d’organisation pédagogique');
            }
        });
    }
}
