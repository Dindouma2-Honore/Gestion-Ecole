<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Models\CategorieMatiere;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CatalogueMatiereService
{
    private const MATIERE_COMPONENT = 'App\\Modules\\Pedagogie\\Filament\\Resources\\MatiereResource';

    private const CATEGORIE_COMPONENT = 'App\\Modules\\Pedagogie\\Filament\\Resources\\CategorieMatiereResource';

    public function __construct(private OrganisationPedagogiqueContext $context, private AuditServiceContract $audit) {}

    public function enregistrer(array $data, ?int $id = null): Matiere
    {
        abort_unless($this->context->canManage(self::MATIERE_COMPONENT), 403);
        $data = Validator::make($data, [
            'nom' => 'required|string|max:150', 'code' => ['nullable', 'string', 'max:20', Rule::unique('matieres', 'code')->ignore($id)],
            'nature' => 'required|in:matiere,domaine,activite', 'categorie_matiere_id' => 'required|exists:categories_matieres,id',
            'coefficient_defaut' => ['nullable', 'numeric', 'min:0.01', 'max:999.99'],
            'description' => 'nullable|string', 'actif' => 'required|boolean',
        ])->validate();

        if (! filled($data['code'] ?? null)) {
            unset($data['code']);
        }
        if (! is_numeric($data['coefficient_defaut'] ?? null)) {
            unset($data['coefficient_defaut']);
        }
        $coefficient = (float) ($data['coefficient_defaut'] ?? 1);

        return DB::transaction(function () use ($data, $id, $coefficient) {
            $m = $id ? Matiere::lockForUpdate()->findOrFail($id) : new Matiere(['coefficient' => $coefficient, 'coefficient_defaut' => $coefficient]);
            $m->fill($data)->save();
            $this->audit->enregistrer($m, 'Enregistrement d’une matière ou d’un domaine');

            return $m;
        });
    }

    public function enregistrerCategorie(array $data, ?int $id = null): CategorieMatiere
    {
        abort_unless($this->context->canManage(self::CATEGORIE_COMPONENT), 403);
        $data = Validator::make($data, [
            'nom' => 'required|string|max:100', 'description' => 'nullable|string', 'actif' => 'required|boolean',
        ])->validate();

        return DB::transaction(function () use ($data, $id) {
            $categorie = $id ? CategorieMatiere::lockForUpdate()->findOrFail($id) : new CategorieMatiere;
            $categorie->fill($data)->save();
            $this->audit->enregistrer($categorie, 'Enregistrement d’une catégorie de matière');

            return $categorie;
        });
    }

    public function supprimerCategorie(int $id): void
    {
        abort_unless($this->context->canManage(self::CATEGORIE_COMPONENT), 403);

        DB::transaction(function () use ($id): void {
            $categorie = CategorieMatiere::lockForUpdate()->findOrFail($id);

            if ($categorie->code === 'NON_CLASSEE') {
                throw ValidationException::withMessages(['categorie' => 'La catégorie par défaut des matières sans classement ne peut pas être supprimée.']);
            }

            if (Matiere::where('categorie_matiere_id', $categorie->id)->exists()) {
                throw ValidationException::withMessages(['categorie' => 'Cette catégorie est utilisée par au moins une matière. Déclassez d’abord ces matières.']);
            }

            $categorie->delete();
            $this->audit->enregistrer($categorie, 'Suppression d’une catégorie de matière');
        });
    }
}
