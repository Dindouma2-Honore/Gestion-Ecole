<?php

namespace App\Modules\Pedagogie\Services;

use App\Filament\Support\ModuleAccess;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\RH\Contracts\EnseignantServiceInterface;
use App\Modules\RH\Contracts\EnseignantsPedagogiquesReadContract;
use App\Modules\Scolarite\Contracts\ClassesPedagogiquesReadContract;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class ProgrammeAccessService
{
    public function enseignantId(): ?int
    {
        return Auth::id() ? app(EnseignantServiceInterface::class)->getEnseignantIdPourUtilisateur((int) Auth::id()) : null;
    }

    public function supervise(): bool
    {
        return Auth::user()?->hasAnyRole(['Fondateur', 'Directeur']) === true;
    }

    public function autorise(): bool
    {
        return Auth::user()?->statut === 'actif' && ModuleAccess::canAccessComponent(Auth::user(), 'App\\Modules\\Pedagogie\\Filament\\Resources\\ProgrammeResource');
    }

    public function offres(int $anneeId): array
    {
        if (! $this->autorise()) {
            return [];
        }
        $teacher = $this->enseignantId();
        if (! $teacher || ! (app(EnseignantsPedagogiquesReadContract::class)->tous()[$teacher]['actif'] ?? false)) {
            return [];
        }
        $date = today()->toDateString();
        $affectations = AffectationPedagogique::with('matieresSpecialisees')->where('enseignant_id', $teacher)->where('annee_scolaire_id', $anneeId)->where('actif', true)
            ->where(fn ($q) => $q->whereNull('date_debut')->orWhere('date_debut', '<=', $date))->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', $date))->get();
        $classes = app(ClassesPedagogiquesReadContract::class)->pourIds($affectations->pluck('classe_id')->all(), $anneeId);
        $offers = OffrePedagogique::with('matiere')->where('annee_scolaire_id', $anneeId)->whereIn('niveau_id', array_column($classes, 'niveau_id'))->orderByRaw('classe_id IS NULL')->get();
        $result = [];
        foreach ($classes as $class) {
            if ($class['statut'] !== 'active') {
                continue;
            }
            $effective = $offers->filter(fn ($o) => (int) $o->niveau_id === (int) $class['niveau_id'] && (! $o->classe_id || (int) $o->classe_id === (int) $class['id']))->unique('matiere_id');
            foreach ($effective as $o) {
                if (! $o->actif || ! $o->matiere?->actif) {
                    continue;
                }
                $allowed = $affectations->contains(fn ($a) => (int) $a->classe_id === (int) $class['id'] && ($a->type === 'principal' || ($a->type === 'matiere' && (int) $a->matiere_id === (int) $o->matiere_id) || ($a->type === 'assistant' && ($a->matieresSpecialisees->isEmpty() || $a->matieresSpecialisees->contains('id', $o->matiere_id)))));
                if ($allowed) {
                    $result[$class['id'].':'.$o->id] = ['offre_id' => $o->id, 'classe_id' => $class['id'], 'niveau_id' => $class['niveau_id'], 'matiere_id' => $o->matiere_id, 'enseignant_id' => $teacher, 'annee_scolaire_id' => $anneeId, 'label' => $o->matiere->nom.' — '.$class['nom']];
                }
            }
        }

        return $result;
    }

    public function contexte(int $anneeId, int $classeId, int $offreId): array
    {
        abort_unless($this->autorise(), 403);
        $context = $this->offres($anneeId)[$classeId.':'.$offreId] ?? null;
        abort_unless($context, 403, 'Cette offre ne relève pas de vos affectations actives.');
        abort_unless(app(AnneeScolaireServiceContract::class)->ecritureAutorisee($anneeId, Auth::user()), 403);

        return $context;
    }

    public function visibleQuery(): Builder
    {
        $q = Programme::query();
        if (! $this->autorise()) {
            return $q->whereRaw('1=0');
        }
        if ($this->supervise()) {
            return $q->when(! Auth::user()->hasRole('Fondateur'), fn ($q) => $q->where('niveau_id', Auth::user()->niveau_id ?? -1));
        }

        return $q->where('enseignant_id', $this->enseignantId() ?? -1);
    }

    public function modifier(Programme $p): void
    {
        abort_unless($this->visibleQuery()->whereKey($p->id)->exists() && (int) $p->enseignant_id === $this->enseignantId(), 403);
        $this->contexte((int) $p->annee_scolaire_id,(int) $p->classe_id,(int) $p->offre_pedagogique_id);
    }
}
