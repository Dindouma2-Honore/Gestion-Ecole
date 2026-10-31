<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Pedagogie\Contracts\AffectationPedagogiqueServiceInterface;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\OrganisationCycle;
use App\Modules\Pedagogie\Services\OffrePedagogiqueService;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create(['statut' => 'actif']);
    $user->assignRole('Fondateur');
    $this->actingAs($user);

    $this->annee = AnneeScolaire::create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'statut' => 'active',
    ]);

    $this->cyclePrimaire = Cycle::create(['nom' => 'Primaire', 'code' => 'PRI', 'ordre' => 1, 'actif' => true]);
    OrganisationCycle::create(['cycle_id' => $this->cyclePrimaire->id, 'mode' => 'classe']);
    $this->niveauCM1 = Niveau::create(['nom' => 'CM1', 'code' => 'CM1', 'cycle_id' => $this->cyclePrimaire->id, 'ordre' => 5]);
    $this->classeCM1 = Classe::create([
        'nom' => 'CM1 A',
        'code' => 'CM1-A',
        'niveau_id' => $this->niveauCM1->id,
        'annee_scolaire_id' => $this->annee->id,
        'capacite_max' => 35,
        'statut' => 'active',
    ]);

    $this->cycleMaternelle = Cycle::create(['nom' => 'Maternelle', 'code' => 'MAT', 'ordre' => 0, 'actif' => true]);
    OrganisationCycle::create(['cycle_id' => $this->cycleMaternelle->id, 'mode' => 'classe']);
    $this->niveauNursery = Niveau::create(['nom' => 'Nursery', 'code' => 'NUR', 'cycle_id' => $this->cycleMaternelle->id, 'ordre' => 1]);
    $this->classeNursery = Classe::create([
        'nom' => 'Nursery A',
        'code' => 'NUR-A',
        'niveau_id' => $this->niveauNursery->id,
        'annee_scolaire_id' => $this->annee->id,
        'capacite_max' => 20,
        'statut' => 'active',
    ]);

    $this->enseignant1 = teacher('Alpha');
    $this->enseignant2 = teacher('Beta');
    $this->enseignant3 = teacher('Gamma');

    $this->matiere = Matiere::create(['nom' => 'Mathématiques', 'code' => 'MATH-CYC', 'coefficient' => 2, 'actif' => true]);
    $this->offre = createOffre($this->annee, $this->niveauCM1, $this->matiere);

    $this->service = app(AffectationPedagogiqueServiceInterface::class);
});

function teacher(string $nom): Enseignant
{
    $e = Employe::create(['matricule' => 'EMP-CYC-'.$nom, 'nom' => $nom, 'prenom' => 'Test', 'poste' => 'Enseignant', 'departement' => 'Pédagogie', 'date_embauche' => '2026-09-01', 'statut' => 'actif']);

    return Enseignant::create(['employe_id' => $e->id, 'statut_contractuel' => 'titulaire']);
}

function createOffre(AnneeScolaire $annee, Niveau $niveau, Matiere $matiere, array $extra = []): OffrePedagogique
{
    return app(OffrePedagogiqueService::class)->enregistrer(array_replace([
        'annee_scolaire_id' => $annee->id,
        'niveau_id' => $niveau->id,
        'matiere_id' => $matiere->id,
        'coefficient_matiere' => 2,
        'actif' => true,
    ], $extra));
}

function affectData(AnneeScolaire $annee, Classe $classe, Enseignant $enseignant, array $extra = []): array
{
    return array_replace([
        'annee_scolaire_id' => $annee->id,
        'classe_id' => $classe->id,
        'enseignant_id' => $enseignant->id,
        'type' => AffectationPedagogique::TYPE_PRINCIPAL,
        'actif' => true,
    ], $extra);
}

it('affecte un enseignant principal en maternelle/primaire sans matiere obligatoire', function (): void {
    $service = app(AffectationPedagogiqueServiceInterface::class);
    $primaire = $service->enregistrer(affectData($this->annee, $this->classeCM1, $this->enseignant1));
    $maternelle = $service->enregistrer(affectData($this->annee, $this->classeNursery, $this->enseignant2));

    expect($primaire)->toBeInstanceOf(AffectationPedagogique::class)
        ->and($primaire->type)->toBe(AffectationPedagogique::TYPE_PRINCIPAL)
        ->and($primaire->enseignant_id)->toBe($this->enseignant1->id)
        ->and($primaire->matiere_id)->toBeNull()
        ->and($primaire->actif)->toBeTrue()
        ->and($maternelle->type)->toBe(AffectationPedagogique::TYPE_PRINCIPAL)
        ->and($maternelle->classe_id)->toBe($this->classeNursery->id);

    // Le chemin canonique ne synchronise jamais la colonne legacy classes.professeur_principal_id.
    $professeursPrincipaux = DB::table('classes')->whereIn('id', [$this->classeCM1->id, $this->classeNursery->id])->pluck('professeur_principal_id');
    expect($professeursPrincipaux)->each->toBeNull();
});

it('interdit d’avoir deux enseignants principaux actifs pour la meme classe', function (): void {
    $service = app(AffectationPedagogiqueServiceInterface::class);
    $service->enregistrer(affectData($this->annee, $this->classeCM1, $this->enseignant1));

    expect(fn () => $service->enregistrer(affectData($this->annee, $this->classeCM1, $this->enseignant2)))
        ->toThrow(ValidationException::class);
});

it('permet de remplacer l’enseignant principal en desactivant l’ancien', function (): void {
    $service = app(AffectationPedagogiqueServiceInterface::class);
    $aff1 = $service->enregistrer(affectData($this->annee, $this->classeCM1, $this->enseignant1));
    $service->enregistrer(affectData($this->annee, $this->classeCM1, $this->enseignant1, ['actif' => false]), $aff1->getKey());
    $aff2 = $service->enregistrer(affectData($this->annee, $this->classeCM1, $this->enseignant2));

    expect($aff1->fresh()->actif)->toBeFalse()
        ->and($aff2->actif)->toBeTrue()
        ->and($aff2->enseignant_id)->toBe($this->enseignant2->id);

    $principalActif = AffectationPedagogique::query()->actif()->principal()
        ->where('classe_id', $this->classeCM1->id)
        ->where('annee_scolaire_id', $this->annee->id)->sole();
    expect($principalActif->enseignant_id)->toBe($this->enseignant2->id);
});

it('permet d’affecter plusieurs assistants generalistes et specialises', function (): void {
    $matiereAnglais = Matiere::create(['nom' => 'Anglais', 'code' => 'ANG-CYC', 'coefficient' => 2, 'actif' => true]);
    createOffre($this->annee, $this->niveauCM1, $matiereAnglais);

    $service = app(AffectationPedagogiqueServiceInterface::class);
    $generaliste = $service->enregistrer(affectData($this->annee, $this->classeCM1, $this->enseignant2, [
        'type' => AffectationPedagogique::TYPE_ASSISTANT,
    ]));
    $specialise = $service->enregistrer(affectData($this->annee, $this->classeCM1, $this->enseignant3, [
        'type' => AffectationPedagogique::TYPE_ASSISTANT,
        'matiere_ids' => [$matiereAnglais->id],
    ]));

    $assistants = AffectationPedagogique::query()->where('type', AffectationPedagogique::TYPE_ASSISTANT)
        ->where('classe_id', $this->classeCM1->id)->where('actif', true)->get();

    expect($assistants)->toHaveCount(2)
        ->and($generaliste->type)->toBe(AffectationPedagogique::TYPE_ASSISTANT)
        ->and($generaliste->matiere_id)->toBeNull()
        ->and($generaliste->matieresSpecialisees)->toHaveCount(0)
        ->and($specialise->type)->toBe(AffectationPedagogique::TYPE_ASSISTANT)
        ->and($specialise->matieresSpecialisees->pluck('id')->all())->toBe([$matiereAnglais->id]);

    // L’assistant généraliste couvre toutes les matières de l’offre, le spécialisé seulement les siennes.
    expect($service->estAutorise($this->enseignant2->id, $this->classeCM1->id, $matiereAnglais->id, $this->annee->id, '2026-10-01'))->toBeTrue()
        ->and($service->estAutorise($this->enseignant3->id, $this->classeCM1->id, $matiereAnglais->id, $this->annee->id, '2026-10-01'))->toBeTrue()
        ->and($service->estAutorise($this->enseignant3->id, $this->classeCM1->id, $this->matiere->id, $this->annee->id, '2026-10-01'))->toBeFalse()
        ->and($service->estAutorise($this->enseignant2->id, $this->classeCM1->id, $this->matiere->id, $this->annee->id, '2026-10-01'))->toBeTrue();
});

it('affecte un enseignant par matiere pour le secondaire', function (): void {
    $cycleSecondaire = Cycle::create(['nom' => 'Secondaire', 'code' => 'COL', 'ordre' => 3, 'actif' => true]);
    OrganisationCycle::create(['cycle_id' => $cycleSecondaire->id, 'mode' => 'matiere']);
    $niveau6e = Niveau::create(['nom' => '6e', 'code' => '6E', 'cycle_id' => $cycleSecondaire->id, 'ordre' => 7]);
    $classe6e = Classe::create([
        'nom' => '6e A',
        'code' => '6E-A',
        'niveau_id' => $niveau6e->id,
        'annee_scolaire_id' => $this->annee->id,
        'capacite_max' => 40,
        'statut' => 'active',
    ]);
    $matiereMath = Matiere::create(['nom' => 'Mathématiques 6e', 'code' => 'MATH-6E', 'coefficient' => 4, 'actif' => true]);
    $offre = app(OffrePedagogiqueService::class)->enregistrer([
        'annee_scolaire_id' => $this->annee->id,
        'niveau_id' => $niveau6e->id,
        'matiere_id' => $matiereMath->id,
        'coefficient_matiere' => 4,
        'actif' => true,
    ]);

    $affectation = app(AffectationPedagogiqueServiceInterface::class)->enregistrer(affectData($this->annee, $classe6e, $this->enseignant2, [
        'type' => AffectationPedagogique::TYPE_MATIERE,
        'matiere_id' => $matiereMath->id,
    ]));

    expect($affectation->type)->toBe(AffectationPedagogique::TYPE_MATIERE)
        ->and($affectation->classe_id)->toBe($classe6e->id)
        ->and($affectation->matiere_id)->toBe($matiereMath->id)
        ->and($affectation->offre_pedagogique_id)->toBe($offre->id)
        ->and((float) $offre->coefficient_matiere)->toBe(4.0)
        ->and($affectation->actif)->toBeTrue();
});

it('interdit une affectation de matiere sans offre pedagogique', function (): void {
    $cycleSecondaire = Cycle::create(['nom' => 'Secondaire sans offre', 'code' => 'COL-2', 'ordre' => 4, 'actif' => true]);
    OrganisationCycle::create(['cycle_id' => $cycleSecondaire->id, 'mode' => 'matiere']);
    $niveau5e = Niveau::create(['nom' => '5e', 'code' => '5E', 'cycle_id' => $cycleSecondaire->id, 'ordre' => 6]);
    $classe5e = Classe::create([
        'nom' => '5e A',
        'code' => '5E-A',
        'niveau_id' => $niveau5e->id,
        'annee_scolaire_id' => $this->annee->id,
        'capacite_max' => 40,
        'statut' => 'active',
    ]);
    $matiereSansOffre = Matiere::create(['nom' => 'Histoire', 'code' => 'HIST-CYC', 'coefficient' => 2, 'actif' => true]);

    expect(fn () => app(AffectationPedagogiqueServiceInterface::class)->enregistrer(affectData($this->annee, $classe5e, $this->enseignant2, [
        'type' => AffectationPedagogique::TYPE_MATIERE,
        'matiere_id' => $matiereSansOffre->id,
    ])))->toThrow(ValidationException::class);
});

it('refuse un enseignant inactif du personnel', function (): void {
    $this->enseignant1->employe->update(['statut' => 'inactif']);

    expect(fn () => app(AffectationPedagogiqueServiceInterface::class)->enregistrer(affectData($this->annee, $this->classeCM1, $this->enseignant1)))
        ->toThrow(ValidationException::class);
});

it('interdit l’ecriture sur une annee scolaire archivee', function (): void {
    $this->annee->update(['statut' => 'archivee']);

    expect(fn () => app(AffectationPedagogiqueServiceInterface::class)->enregistrer(affectData($this->annee, $this->classeCM1, $this->enseignant1)))
        ->toThrow(ValidationException::class);
});

it('interdit le doublon exact d’une affectation sur la meme periode', function (): void {
    $service = app(AffectationPedagogiqueServiceInterface::class);
    $service->enregistrer(affectData($this->annee, $this->classeCM1, $this->enseignant1, ['type' => AffectationPedagogique::TYPE_ASSISTANT]));

    expect(fn () => $service->enregistrer(affectData($this->annee, $this->classeCM1, $this->enseignant1, ['type' => AffectationPedagogique::TYPE_ASSISTANT])))
        ->toThrow(ValidationException::class);
});
