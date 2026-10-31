<?php

namespace App\Modules\Pedagogie\Services;

use App\Filament\Support\ModuleAccess;
use App\Modules\Pedagogie\Models\DevoirPedagogique;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SessionProgressService
{
    public function programme(Seance $s): ?Programme
    {
        $e = $s->emploiDuTemps;

        return Programme::where('annee_scolaire_id', $e->annee_scolaire_id)->where('matiere_id', $e->matiere_id)->where('classe_id', $e->classe_id)
            ->where('enseignant_id', $s->enseignant_id)->where('statut', 'valide')->where(fn ($q) => $q->where('analyse_statut', 'valide')->orWhereNull('analyse_statut'))->orderByDesc('id')->first();
    }

    public function seances(): Builder
    {
        $access = app(ProgrammeAccessService::class);

        return Seance::with('emploiDuTemps.matiere')->where('statut', 'dispensee')->where('enseignant_id', $access->autorise() && ModuleAccess::canAccessComponent(Auth::user(), 'App\\Modules\\Pedagogie\\Filament\\Resources\\ProgressionResource') ? ($access->enseignantId() ?? -1) : -1);
    }

    public function enregistrer(array $data, ?int $id = null): Progression
    {
        $data = Validator::make($data, [
            'seance_id' => 'required|integer', 'chapitre_id' => 'required|integer', 'etat_avancement' => 'required|in:commencee,en_cours,terminee',
            'contenu_couvert' => 'required|string|max:20000', 'observation' => 'nullable|string|max:10000', 'devoir_donne' => 'required|boolean',
            'type_devoir' => 'required_if:devoir_donne,true|nullable|in:exercice,maison,recherche,autre',
            'consigne' => 'required_if:devoir_donne,true|nullable|string|max:255', 'description_devoir' => 'nullable|string|max:10000',
            'date_remise' => 'required_if:devoir_donne,true|nullable|date_format:Y-m-d',
        ])->validate();

        return DB::transaction(function () use ($data, $id): Progression {
            $s = $this->seances()->lockForUpdate()->findOrFail($data['seance_id']);
            $existing = $id ? Progression::lockForUpdate()->findOrFail($id) : null;
            if ($existing) {
                abort_unless((int) $existing->seance_id === (int) $s->id && (int) $existing->saisi_par === (int) Auth::id(), 403);
                abort_unless($existing->created_at->gte(now()->subHours(24)), 403, 'La période de correction de 24 heures est dépassée.');
            } elseif (Progression::where('seance_id', $s->id)->exists()) {
                throw ValidationException::withMessages(['seance_id' => 'Cette séance possède déjà une progression.']);
            }
            $element = ProgrammeChapitre::with('programme')->findOrFail($data['chapitre_id']);
            $p = $existing ? ProgrammeChapitre::findOrFail($existing->chapitre_id)->programme : $this->programme($s);
            abort_unless($p && (int) $element->programme_id === (int) $p->id && $p->statut === 'valide' && ! $p->chapitres()->where('parent_id', $element->id)->exists(), 422, 'Choisissez une leçon ou un objectif du programme validé de cette séance.');
            $offreId = $p->offre_pedagogique_id;
            if (! $offreId) {
                $offreId = collect(app(ProgrammeAccessService::class)->offres((int) $p->annee_scolaire_id))->first(fn ($o) => (int) $o['classe_id'] === (int) $p->classe_id && (int) $o['matiere_id'] === (int) $p->matiere_id)['offre_id'] ?? null;
            }
            app(ProgrammeAccessService::class)->contexte((int) $p->annee_scolaire_id, (int) $p->classe_id, (int) $offreId);
            if ($data['devoir_donne'] && $data['date_remise'] < $s->date_seance->format('Y-m-d')) {
                throw ValidationException::withMessages(['date_remise' => 'La remise doit suivre la séance.']);
            }
            $before = $existing?->toArray();
            $progression = $existing ?? new Progression;
            $progression->fill(['seance_id' => $s->id, 'chapitre_id' => $element->id, 'etat_avancement' => $data['etat_avancement'], 'chapitre_termine' => $data['etat_avancement'] === 'terminee', 'contenu_couvert' => $data['contenu_couvert'], 'observation' => $data['observation'] ?? null, 'saisi_par' => Auth::id(), 'devoirs_donnes' => $data['devoir_donne'] ? $data['consigne'] : null])->save();
            if ($data['devoir_donne']) {
                $devoir = DevoirPedagogique::updateOrCreate(['progression_id' => $progression->id], ['seance_id' => $s->id, 'classe_id' => $p->classe_id, 'enseignant_id' => $s->enseignant_id, 'offre_pedagogique_id' => $offreId, 'annee_scolaire_id' => $p->annee_scolaire_id, 'type' => $data['type_devoir'], 'consigne' => $data['consigne'], 'description' => $data['description_devoir'] ?? null, 'date_remise' => $data['date_remise']]);
                app(AuditServiceContract::class)->enregistrer($devoir, 'Enregistrement du devoir de séance');
            } elseif (DevoirPedagogique::where('progression_id', $progression->id)->exists()) {
                throw ValidationException::withMessages(['devoir_donne' => 'Un devoir existe déjà : conservez-le pour préserver son historique.']);
            }
            $s->update(['progression_renseignee' => true]);
            app(AuditServiceContract::class)->enregistrer($progression, ($before ? 'Correction de progression ; état précédent : '.json_encode($before,JSON_UNESCAPED_UNICODE) : 'Déclaration de progression après séance'));

            return $progression;
        });
    }
}
