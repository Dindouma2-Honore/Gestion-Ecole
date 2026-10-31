<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\AffectationPedagogiqueServiceInterface;
use App\Modules\Pedagogie\Enums\TypeAffectationPedagogique;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AffectationPedagogiqueService implements AffectationPedagogiqueServiceInterface
{
    private const COMPONENT = 'App\\Modules\\Pedagogie\\Filament\\Resources\\AffectationPedagogiqueResource';

    public function __construct(private OrganisationPedagogiqueContext $context, private OffrePedagogiqueService $offres, private AuditServiceContract $audit) {}

    public function enregistrer(array $data, ?int $id = null): AffectationPedagogique
    {
        $this->context->reset();
        $data = Validator::make($data, [
            'annee_scolaire_id' => 'required|integer', 'classe_id' => 'required|integer', 'enseignant_id' => 'required|integer',
            'type' => ['required', Rule::enum(TypeAffectationPedagogique::class)], 'matiere_id' => 'nullable|integer',
            'matiere_ids' => 'nullable|array', 'matiere_ids.*' => 'integer|distinct', 'date_debut' => 'nullable|date_format:Y-m-d',
            'date_fin' => 'nullable|date_format:Y-m-d', 'actif' => 'required|boolean',
        ])->validate();
        $year = $this->context->assertWrite((int) $data['annee_scolaire_id'], self::COMPONENT);
        $class = $this->context->classes((int) $data['annee_scolaire_id'])[$data['classe_id']] ?? null;
        if (! $class || $class['statut'] !== 'active') {
            $this->invalid('classe_id', 'Choisissez une classe active accessible de cette année.');
        }
        $teacher = $this->context->enseignants()[$data['enseignant_id']] ?? null;
        if (! $teacher || ! $teacher['actif']) {
            $this->invalid('enseignant_id', 'Choisissez un enseignant actif du personnel RH.');
        }
        $mode = $this->context->modeClasse((int) $data['annee_scolaire_id'], (int) $data['classe_id']);
        if (! $mode) {
            $this->invalid('classe_id', 'Configurez le mode de ce cycle dans Organisation pédagogique.');
        }
        if (($mode === 'matiere') !== ($data['type'] === 'matiere')) {
            $this->invalid('type', 'Ce rôle ne correspond pas au mode du cycle.');
        }
        $data['date_debut'] = $data['date_debut'] ?? substr($year['date_debut'], 0, 10);
        $data['date_fin'] = $data['date_fin'] ?? substr($year['date_fin'], 0, 10);
        if ($data['date_debut'] > $data['date_fin'] || $data['date_debut'] < substr($year['date_debut'], 0, 10) || $data['date_fin'] > substr($year['date_fin'], 0, 10)) {
            $this->invalid('date_fin', 'Les dates doivent être ordonnées et comprises dans l’année scolaire.');
        }
        $specialites = $data['matiere_ids'] ?? [];
        unset($data['matiere_ids']);
        if ($data['type'] !== 'assistant' && $specialites) {
            $this->invalid('matiere_ids', 'Seul un assistant peut avoir un périmètre spécialisé.');
        }
        if ($data['type'] === 'matiere' && empty($data['matiere_id'])) {
            $this->invalid('matiere_id', 'La matière est obligatoire pour cette affectation.');
        }
        if ($data['type'] !== 'matiere') {
            $data['matiere_id'] = null;
        }

        return DB::transaction(function () use ($data, $id, $specialites): AffectationPedagogique {
            $mode = $this->context->modeClasse((int) $data['annee_scolaire_id'], (int) $data['classe_id']);
            if (! $mode || (($mode === 'matiere') !== ($data['type'] === 'matiere'))) {
                $this->invalid('type', 'Le mode du cycle a changé. Rechargez le formulaire.');
            }
            $this->context->lock((int) $data['annee_scolaire_id']);
            $record = $id ? AffectationPedagogique::lockForUpdate()->findOrFail($id) : new AffectationPedagogique;
            if ($record->exists) {
                $this->context->assertWrite((int) $record->annee_scolaire_id, self::COMPONENT);
                if (! isset($this->context->classes((int) $record->annee_scolaire_id)[$record->classe_id])) {
                    abort(403);
                }
                // Identity is historical: close an assignment and create another to change its context.
                foreach (['annee_scolaire_id', 'classe_id', 'enseignant_id', 'type', 'matiere_id'] as $key) {
                    if ((string) $record->$key !== (string) ($data[$key] ?? '')) {
                        $this->invalid($key, 'Conservez le contexte historique ; désactivez cette affectation puis créez la nouvelle.');
                    }
                }
            }
            $offre = null;
            foreach (array_merge($specialites, $data['matiere_id'] ? [$data['matiere_id']] : []) as $matiereId) {
                if (! Matiere::whereKey($matiereId)->where('actif', true)->exists()) {
                    $this->invalid('matiere_id', 'Choisissez une matière ou un domaine actif.');
                }
                $candidate = $this->offres->effective((int) $data['annee_scolaire_id'], (int) $data['classe_id'], (int) $matiereId);
                if (! $candidate) {
                    $this->invalid('matiere_id', 'Ajoutez d’abord cette matière à l’offre pédagogique du niveau ou de la classe.');
                }
                if ($data['matiere_id']) {
                    $offre = $candidate;
                }
            }
            $q = AffectationPedagogique::where('annee_scolaire_id', $data['annee_scolaire_id'])->where('classe_id', $data['classe_id'])->where('actif', true)
                ->when($id, fn ($q) => $q->whereKeyNot($id))
                ->where(fn ($q) => $q->whereNull('date_debut')->orWhere('date_debut', '<=', $data['date_fin']))
                ->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', $data['date_debut']));
            if ($data['actif']) {
                if ($data['type'] === 'principal' && (clone $q)->where('type', 'principal')->exists()) {
                    $this->invalid('type', 'Cette classe possède déjà un enseignant principal sur cette période.');
                }
                if ((clone $q)->where('enseignant_id', $data['enseignant_id'])->where('type', $data['type'])->where('matiere_id', $data['matiere_id'])->exists()) {
                    $this->invalid('enseignant_id', 'Cette affectation existe déjà sur cette période.');
                }
            }
            $record->fill($data + ['offre_pedagogique_id' => $offre?->id])->save();
            $record->matieresSpecialisees()->sync($specialites);
            $this->audit->enregistrer($record, 'Enregistrement d’une affectation pédagogique');

            return $record->refresh();
        });
    }

    public function estAutorise(int $enseignantId, int $classeId, int $matiereId, int $anneeId, string $date): bool
    {
        if (! ($this->context->enseignants()[$enseignantId]['actif'] ?? false) || ! $this->offres->effective($anneeId, $classeId, $matiereId)) {
            return false;
        }

        return AffectationPedagogique::where('enseignant_id', $enseignantId)->where('classe_id', $classeId)->where('annee_scolaire_id', $anneeId)->where('actif', true)
            ->where(fn ($q) => $q->whereNull('date_debut')->orWhere('date_debut', '<=', $date))
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', $date))
            ->where(function ($q) use ($matiereId): void {
                $q->where('type', 'principal')->orWhere(fn ($q) => $q->where('type', 'matiere')->where('matiere_id', $matiereId))
                    ->orWhere(fn ($q) => $q->where('type', 'assistant')->where(fn ($q) => $q->doesntHave('matieresSpecialisees')->orWhereHas('matieresSpecialisees', fn ($q) => $q->where('matieres.id',$matiereId))));
            })->exists();
    }

    private function invalid(string $key,string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
