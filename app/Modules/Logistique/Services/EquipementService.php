<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Services;

use App\Modules\Logistique\Contracts\CatalogueMaterielInterface;
use App\Modules\Logistique\Contracts\EquipementServiceInterface;
use App\Modules\Logistique\Contracts\MaintenanceServiceInterface;
use App\Modules\Logistique\Enums\EtatPhysiqueMateriel;
use App\Modules\Logistique\Enums\NatureMateriel;
use App\Modules\Logistique\Enums\StatutOperationnelMateriel;
use App\Modules\Logistique\Models\Equipement;
use App\Modules\Logistique\Models\EquipementHistoriqueLocalisation;
use App\Modules\Logistique\Models\EquipementHistoriqueStatut;
use App\Modules\Logistique\Models\EquipementPhoto;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\NumerotationServiceContract as SocleNumerotationServiceContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EquipementService implements EquipementServiceInterface
{
    public function __construct(
        private readonly MaintenanceServiceInterface $maintenance,
        private readonly AuditServiceContract $audit,
        private readonly CatalogueMaterielInterface $catalogue,
        private readonly DocumentServiceContract $document,
    ) {}

    public function enregistrerEquipement(array $donnees): object
    {
        if (array_key_exists('categorie_id', $donnees) && filled($donnees['categorie_id'])) {
            // La catégorie doit exister et être active : aucune création
            // implicite, aucun contournement du catalogue.
            $this->catalogue->validerAffectation((int) $donnees['categorie_id']);
        }

        if (array_key_exists('etat', $donnees) && filled($donnees['etat'])) {
            $etat = $donnees['etat'];
            $enumEtat = is_string($etat) ? EtatPhysiqueMateriel::tryFrom($etat) : ($etat instanceof EtatPhysiqueMateriel ? $etat : null);
            if (! $enumEtat instanceof EtatPhysiqueMateriel) {
                throw new InvalidArgumentException("État physique invalide : " . (is_string($etat) ? $etat : 'valeur non reconnue'));
            }
            $donnees['etat'] = $enumEtat->value;
        } else {
            $donnees['etat'] = EtatPhysiqueMateriel::Bon->value;
        }

        if (array_key_exists('statut_operationnel', $donnees) && filled($donnees['statut_operationnel'])) {
            $statut = $donnees['statut_operationnel'];
            $enumStatut = is_string($statut) ? StatutOperationnelMateriel::tryFrom($statut) : ($statut instanceof StatutOperationnelMateriel ? $statut : null);
            if (! $enumStatut instanceof StatutOperationnelMateriel) {
                throw new InvalidArgumentException("Statut opérationnel invalide : " . (is_string($statut) ? $statut : 'valeur non reconnue'));
            }
            $donnees['statut_operationnel'] = $enumStatut->value;
        } else {
            $donnees['statut_operationnel'] = StatutOperationnelMateriel::EnStock->value;
        }

        return Equipement::create($donnees);
    }

    public function deplacer(int $equipementId, int $nouvelleSalleId, ?string $motif = null, ?int $responsableId = null): void
    {
        DB::transaction(function () use ($equipementId, $nouvelleSalleId, $motif, $responsableId): void {
            $equipement = Equipement::query()->lockForUpdate()->findOrFail($equipementId);
            $ancienneSalle = $equipement->salle_id;

            if ((int) $ancienneSalle === $nouvelleSalleId) {
                return;
            }

            $equipement->update(['salle_id' => $nouvelleSalleId, 'responsable_id' => $responsableId ?? $equipement->responsable_id]);

            EquipementHistoriqueLocalisation::create([
                'equipement_id' => $equipementId,
                'ancienne_salle_id' => $ancienneSalle,
                'nouvelle_salle_id' => $nouvelleSalleId,
                'responsable_id' => $responsableId,
                'effectue_par' => Auth::id(),
                'motif' => $motif,
                'date_deplacement' => now(),
            ]);
        });
    }

    public function declarerPanne(int $equipementId, string $description): object
    {
        $equipement = Equipement::findOrFail($equipementId);
        $this->changerStatut($equipementId, StatutOperationnelMateriel::EnMaintenance->value, "Panne signalée: {$description}");

        // Rétrocompatibilité LOT 04A : une panne dégrade l'état physique
        // (sauf s'il est déjà irréparable), sans jamais le confondre avec
        // le statut opérationnel qui vient d'être basculé en maintenance.
        if (! in_array($equipement->etat, [EtatPhysiqueMateriel::AReparer->value, EtatPhysiqueMateriel::HorsService->value, EtatPhysiqueMateriel::MisAuRebut->value], true)) {
            $equipement->update(['etat' => EtatPhysiqueMateriel::AReparer->value]);
        }

        return $this->maintenance->signalerPanne($equipementId, $description);
    }

    public function mettreAuRebut(int $equipementId, string $motif): void
    {
        $equipement = Equipement::findOrFail($equipementId);
        $this->changerStatut($equipementId, StatutOperationnelMateriel::Reforme->value, $motif);
        $equipement->update(['etat' => 'mis_au_rebut', 'date_mise_au_rebut' => now()]);
    }

    public function getValeurPatrimoine(): float
    {
        return (float) Equipement::where('statut_operationnel', '!=', StatutOperationnelMateriel::Reforme->value)
            ->where('etat', '!=', 'mis_au_rebut')
            ->sum('valeur_acquisition');
    }

    public function getEquipementsSousGarantie(): Collection
    {
        return Equipement::whereNotNull('garantie_fin')
            ->where('garantie_fin', '>=', now())
            ->get();
    }

    public function natureEquipement(int $equipementId): ?string
    {
        $equipement = Equipement::query()->findOrFail($equipementId);

        if (blank($equipement->categorie_id)) {
            return null;
        }

        return $this->catalogue->nature((int) $equipement->categorie_id);
    }

    public function modifierEtatPhysique(int $equipementId, string|EtatPhysiqueMateriel $etat): void
    {
        $enumEtat = is_string($etat) ? EtatPhysiqueMateriel::tryFrom($etat) : ($etat instanceof EtatPhysiqueMateriel ? $etat : null);

        if (! $enumEtat instanceof EtatPhysiqueMateriel) {
            throw new InvalidArgumentException("État physique invalide : " . (is_string($etat) ? $etat : 'valeur non reconnue'));
        }

        DB::transaction(function () use ($equipementId, $enumEtat): void {
            $equipement = Equipement::query()->lockForUpdate()->findOrFail($equipementId);
            $ancienEtat = $equipement->etat;

            if ($ancienEtat === $enumEtat->value) {
                return;
            }

            $equipement->update(['etat' => $enumEtat->value]);

            $this->audit->enregistrer($equipement, "Modification de l'état physique de l'équipement #{$equipementId} (ancien: {$ancienEtat}, nouveau: {$enumEtat->value})");
        });
    }

    public function changerStatut(int $equipementId, string $nouveauStatut, ?string $motif = null): void
    {
        $enumStatut = StatutOperationnelMateriel::tryFrom($nouveauStatut);
        if (! $enumStatut instanceof StatutOperationnelMateriel) {
            throw new InvalidArgumentException("Statut opérationnel invalide : {$nouveauStatut}");
        }

        DB::transaction(function () use ($equipementId, $enumStatut, $motif): void {
            $equipement = Equipement::query()->lockForUpdate()->findOrFail($equipementId);
            $ancienStatut = $equipement->statut_operationnel ?? StatutOperationnelMateriel::EnStock->value;

            if ($ancienStatut === $enumStatut->value) {
                return;
            }

            $this->validerTransition($ancienStatut, $enumStatut->value);

            $equipement->update(['statut_operationnel' => $enumStatut->value]);

            EquipementHistoriqueStatut::create([
                'equipement_id' => $equipementId,
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => $enumStatut->value,
                'motif' => $motif,
                'effectue_par' => Auth::id(),
            ]);

            $this->audit->enregistrer($equipement, "Changement de statut opérationnel de l'équipement #{$equipementId} de {$ancienStatut} vers {$enumStatut->value}" . ($motif ? " (Motif: {$motif})" : ""));
        });
    }

    public function getHistoriqueStatuts(int $equipementId): Collection
    {
        return EquipementHistoriqueStatut::where('equipement_id', $equipementId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function validerTransition(string $ancien, string $nouveau): void
    {
        if ($ancien === StatutOperationnelMateriel::Reforme->value && $nouveau !== StatutOperationnelMateriel::Reforme->value) {
            throw new InvalidArgumentException("Impossible de changer le statut d'un équipement réformé.");
        }

        $transitions = [
            'en_stock' => ['affecte', 'en_maintenance', 'loue', 'reforme'],
            'affecte' => ['en_stock', 'en_maintenance', 'loue', 'reforme'],
            'en_maintenance' => ['en_stock', 'affecte', 'loue', 'reforme'],
            'loue' => ['en_stock', 'affecte', 'en_maintenance', 'reforme'],
            'reforme' => [],
        ];

        if (! isset($transitions[$ancien]) || ! in_array($nouveau, $transitions[$ancien], true)) {
            throw new InvalidArgumentException("Transition de statut non autorisée de '{$ancien}' vers '{$nouveau}'.");
        }
    }

    public function genererNumeroInventaire(int $equipementId): ?string
    {
        $equipement = Equipement::query()->findOrFail($equipementId);

        // Ne génère pas de numéro si l'équipement n'a pas de catégorie
        // (donnée legacy) ou si la catégorie est consommable.
        if (blank($equipement->categorie_id)) {
            return null;
        }

        $categorie = $this->catalogue->categorie($equipement->categorie_id);

        // Si la catégorie est consommable, on ne force pas l'identifiant
        // d'inventaire ; c'est au futur stock quantitatif de gérer ce cas.
        if ($categorie->nature === NatureMateriel::Consommable->value) {
            return null;
        }

        // Essaie d'utiliser le service de numérotation Socle si configuré.
        try {
            $socleService = app(SocleNumerotationServiceContract::class);
            $format = 'equipement_inventaire';
            $variables = [
                'marque' => $equipement->fournisseur ?? '',
                'modele' => '',
            ];
            $nextNum = $socleService->next($format, $variables);

            // Vérifie l'unicité dans la base Logistique avant de l'utiliser.
            if (! Equipement::where('numero_serie', $nextNum)->exists()) {
                return $nextNum;
            }
        } catch (\Throwable) {
            // Si le service Socle n'est pas configuré ou erreur, on utilise
            // un identifiant stable basé sur l'existant (numéro de série
            // existant ou format générique).
        }

        // Retourne un identifiant stable depuis les données existantes
        // ou un format générique si aucun numéro n'est déjà affecté.
        return $equipement->numero_serie ?: 'ASSET-' . sprintf('%06d', $equipement->id);
    }

    public function ajouterPhoto(int $equipementId, UploadedFile $fichier, string $type = 'principale', ?string $description = null, bool $principal = false): object
    {
        return DB::transaction(function () use ($equipementId, $fichier, $type, $description, $principal): object {
            $equipement = Equipement::query()->lockForUpdate()->findOrFail($equipementId);

            $doc = $this->document->attacher($equipement, $fichier, 'equipement_photo', 'interne');

            $existingCount = EquipementPhoto::where('equipement_id', $equipementId)->count();
            $doBePrincipal = $principal || $existingCount === 0;

            if ($doBePrincipal) {
                EquipementPhoto::where('equipement_id', $equipementId)->update(['principal' => false]);
            }

            $photo = EquipementPhoto::create([
                'equipement_id' => $equipementId,
                'document_id' => $doc->id,
                'type' => $type,
                'description' => $description,
                'principal' => $doBePrincipal,
            ]);

            $this->audit->enregistrer($photo, "Ajout d'une photo pour l'équipement #{$equipementId}");

            return $photo;
        });
    }

    public function definirPhotoPrincipale(int $equipementId, int $photoId): void
    {
        DB::transaction(function () use ($equipementId, $photoId): void {
            $equipement = Equipement::query()->lockForUpdate()->findOrFail($equipementId);
            $photo = EquipementPhoto::where('equipement_id', $equipementId)->whereKey($photoId)->lockForUpdate()->firstOrFail();

            EquipementPhoto::where('equipement_id', $equipementId)->update(['principal' => false]);
            $photo->update(['principal' => true]);

            $this->audit->enregistrer($photo, "Définition de la photo #{$photoId} comme principale pour l'équipement #{$equipementId}");
        });
    }

    public function retirerPhoto(int $photoId): void
    {
        DB::transaction(function () use ($photoId): void {
            $photo = EquipementPhoto::lockForUpdate()->findOrFail($photoId);
            $equipementId = $photo->equipement_id;
            $wasPrincipal = $photo->principal;

            $photo->delete();

            if ($wasPrincipal) {
                $autre = EquipementPhoto::where('equipement_id', $equipementId)->first();
                if ($autre) {
                    $autre->update(['principal' => true]);
                }
            }

            $this->audit->enregistrer($photo, "Retrait de la photo #{$photoId} de l'équipement #{$equipementId}");
        });
    }
}
