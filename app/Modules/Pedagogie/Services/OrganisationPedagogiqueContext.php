<?php

namespace App\Modules\Pedagogie\Services;

use App\Filament\Support\ModuleAccess;
use App\Modules\Pedagogie\Models\OrganisationCycle;
use App\Modules\RH\Contracts\EnseignantsPedagogiquesReadContract;
use App\Modules\Scolarite\Contracts\AcademicStructureCatalogContract;
use App\Modules\Scolarite\Contracts\ClassesPedagogiquesReadContract;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrganisationPedagogiqueContext
{
    private array $cache = [];

    public function __construct(private AcademicStructureCatalogContract $referentiel, private ClassesPedagogiquesReadContract $classes,
        private EnseignantsPedagogiquesReadContract $enseignants, private AnneeScolaireServiceContract $annees) {}

    public function reset(): void
    {
        $this->cache = [];
    }

    public function cycles(): array
    {
        return $this->cache['cycles'] ??= array_map(fn ($item) => ['id' => $item->id, 'nom' => $item->name, 'code' => $item->code, 'actif' => $item->active], $this->referentiel->cycles());
    }

    public function niveaux(): array
    {
        return $this->cache['niveaux'] ??= array_filter(array_map(fn ($item) => ['id' => $item->id, 'nom' => $item->name, 'cycle_id' => $item->parentId], $this->referentiel->niveaux()), function ($n): bool {
            $u = Auth::user();

            return ! $u?->hasAnyRole(['Directeur', 'Enseignant']) || (int) $u->niveau_id === (int) $n['id'];
        });
    }

    public function classes(int $anneeId): array
    {
        return $this->cache['classes-'.$anneeId] ??= $this->classes->pourAnnee($anneeId);
    }

    public function enseignants(): array
    {
        return $this->cache['enseignants'] ??= $this->enseignants->tous();
    }

    public function annees(): array
    {
        return $this->cache['annees'] ??= collect($this->annees->getToutesLesAnnees())->keyBy('id')->all();
    }

    public function anneeCourante(): ?int
    {
        try {
            return $this->annees->getAnneeCouranteId();
        } catch (\Throwable) {
            return null;
        }
    }

    public function modeClasse(int $anneeId, int $classeId): ?string
    {
        $c = $this->classes($anneeId)[$classeId] ?? null;
        $n = $c ? ($this->niveaux()[$c['niveau_id']] ?? null) : null;

        return $n ? OrganisationCycle::where('cycle_id', $n['cycle_id'])->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->value('mode') : null;
    }

    public function canManage(string $component): bool
    {
        $u = Auth::user();

        return $u && ModuleAccess::canAccessComponent($u, $component)
            && $u->hasAnyRole(['Fondateur', 'Directeur']);
    }

    public function assertWrite(int $anneeId, string $component): array
    {
        abort_unless($this->canManage($component), 403);
        $year = $this->annees()[$anneeId] ?? null;
        if (! $year || ! $this->annees->ecritureAutorisee($anneeId, Auth::user())) {
            throw ValidationException::withMessages(['annee_scolaire_id' => 'Cette année scolaire ne permet pas cette modification.']);
        }

        return $year;
    }

    /** Serialize organisation writes in an annual context, including competing principals. */
    public function lock(int $anneeId): void
    {
        DB::table('organisation_pedagogique_verrous')->insertOrIgnore(['annee_scolaire_id' => $anneeId]);
        DB::table('organisation_pedagogique_verrous')->where('annee_scolaire_id', $anneeId)->lockForUpdate()->first();
    }
}
