<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProgramStructureService
{
    public function enregistrer(Programme $p, array $elements, bool $valider = false): Programme
    {
        app(ProgrammeAccessService::class)->modifier($p);

        return DB::transaction(function () use ($p, $elements, $valider): Programme {
            $p = Programme::lockForUpdate()->findOrFail($p->id);
            abort_unless($p->analyse_statut === 'a_valider' && $p->statut !== 'valide', 422, 'Cette version ne peut plus être modifiée. Importez une nouvelle version.');
            $this->remplacer($p, $elements);
            if ($valider) {
                $p->update(['statut' => 'valide', 'analyse_statut' => 'valide', 'valide_le' => now(), 'valide_par' => Auth::id()]);
            }
            app(AuditServiceContract::class)->enregistrer($p, $valider ? 'Validation humaine de la structure du programme' : 'Correction de la structure proposée');

            return $p;
        });
    }

    /** Caller holds programme lock; only draft structures can be replaced. */
    public function remplacer(Programme $p, array $elements): void
    {
        abort_if(in_array($p->statut, ['valide', 'publie']), 422);
        Validator::make(['elements' => $elements], [
            'elements' => 'required|array|min:1|max:500', 'elements.*.titre' => 'required|string|max:200',
            'elements.*.type' => 'required|in:chapitre,unite,sequence,lecon,objectif,competence,activite',
            'elements.*.parent_index' => 'nullable|integer|min:0', 'elements.*.contenu' => 'nullable|string|max:20000',
            'elements.*.volume_horaire_prevu' => 'nullable|numeric|gt:0|max:1000',
        ])->validate();
        $elements = array_values($elements);
        foreach ($elements as $i => $e) {
            if (isset($e['parent_index']) && $e['parent_index'] !== '' && (int) $e['parent_index'] >= $i) {
                throw ValidationException::withMessages(['elements' => 'Le parent doit précéder son élément ; aucune boucle n’est autorisée.']);
            }
        }
        if (Progression::whereIn('chapitre_id', $p->chapitres()->select('id'))->exists()) {
            throw ValidationException::withMessages(['elements' => 'Cette structure possède déjà un historique.']);
        }
        $p->chapitres()->delete();
        $ids = [];
        foreach ($elements as $i => $e) {
            $ids[$i] = $p->chapitres()->create(['titre' => $e['titre'], 'type' => $e['type'], 'ordre' => $i + 1, 'parent_id' => isset($e['parent_index']) && $e['parent_index'] !== '' ? $ids[(int) $e['parent_index']] : null, 'contenu' => $e['contenu'] ?? null, 'volume_horaire_prevu' => $e['volume_horaire_prevu'] ?? null])->id;
        }
    }

    public function proposition(Programme $p): array
    {
        $elements = $p->chapitres()->get();
        $indices = $elements->pluck('id')->flip();

        return $elements->map(fn ($e) => ['titre' => $e->titre, 'type' => $e->type, 'parent_index' => $e->parent_id ? $indices->get($e->parent_id) : null, 'contenu' => $e->contenu, 'volume_horaire_prevu' => $e->volume_horaire_prevu])->all();
    }
}
