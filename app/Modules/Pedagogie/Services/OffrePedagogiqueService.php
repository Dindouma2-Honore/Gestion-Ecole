<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class OffrePedagogiqueService
{
    private const COMPONENT = 'App\\Modules\\Pedagogie\\Filament\\Resources\\OffrePedagogiqueResource';

    public function __construct(private OrganisationPedagogiqueContext $context, private AuditServiceContract $audit) {}

    public function enregistrer(array $data, ?int $id = null): OffrePedagogique
    {
        $this->context->reset();
        $data = Validator::make($data, [
            'annee_scolaire_id' => 'required|integer', 'niveau_id' => 'required|integer', 'classe_id' => 'nullable|integer',
            'matiere_id' => 'required|integer|exists:matieres,id', 'coefficient_matiere' => 'required|numeric|min:0.01|max:999.99',
            'volume_horaire' => 'nullable|numeric|min:0|max:99999.99', 'heures_hebdomadaires' => 'nullable|numeric|min:0|max:168', 'actif' => 'required|boolean',
        ])->validate();
        $this->context->assertWrite((int) $data['annee_scolaire_id'], self::COMPONENT);
        if (! isset($this->context->niveaux()[$data['niveau_id']])) {
            throw ValidationException::withMessages(['niveau_id' => 'Ce niveau n’est pas accessible.']);
        }
        $data['classe_id'] = $data['classe_id'] ?? null;
        $class = $data['classe_id'] ? ($this->context->classes((int) $data['annee_scolaire_id'])[$data['classe_id']] ?? null) : null;
        if ($data['classe_id'] && (! $class || $class['statut'] !== 'active' || (int) $class['niveau_id'] !== (int) $data['niveau_id'])) {
            throw ValidationException::withMessages(['classe_id' => 'Choisissez une classe active de ce niveau et de cette année.']);
        }

        return DB::transaction(function () use ($data, $id, $class): OffrePedagogique {
            $this->context->lock((int) $data['annee_scolaire_id']);
            $record = $id ? OffrePedagogique::lockForUpdate()->findOrFail($id) : new OffrePedagogique;
            if ($record->exists) {
                $this->context->assertWrite((int) $record->annee_scolaire_id, self::COMPONENT);
                foreach (['annee_scolaire_id', 'niveau_id', 'classe_id', 'matiere_id'] as $key) {
                    if ((string) $record->$key !== (string) ($data[$key] ?? '')) {
                        throw ValidationException::withMessages([$key => 'Le contexte d’une offre existante est conservé. Créez une nouvelle offre.']);
                    }
                }
            }
            if (OffrePedagogique::where('annee_scolaire_id', $data['annee_scolaire_id'])->where('niveau_id', $data['niveau_id'])
                ->where('classe_id', $data['classe_id'])->where('matiere_id', $data['matiere_id'])->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
                throw ValidationException::withMessages(['matiere_id' => 'Cette offre existe déjà pour ce contexte. Modifiez-la ou réactivez-la.']);
            }
            $record->fill($data + ['filiere_id' => $class['filiere_id'] ?? null])->save();
            $this->audit->enregistrer($record, 'Enregistrement d’une offre pédagogique annuelle');

            return $record;
        });
    }

    public function effective(int $anneeId, int $classeId, int $matiereId): ?OffrePedagogique
    {
        $class = $this->context->classes($anneeId)[$classeId] ?? null;
        if (! $class) {
            return null;
        }
        $offer = OffrePedagogique::where('annee_scolaire_id', $anneeId)->where('matiere_id', $matiereId)
            ->where(fn ($q) => $q->where('classe_id', $classeId)->orWhere(fn ($q) => $q->whereNull('classe_id')->where('niveau_id', $class['niveau_id'])))
            ->orderByRaw('classe_id IS NULL')->first();

        return $offer?->actif ? $offer : null;
    }
}
