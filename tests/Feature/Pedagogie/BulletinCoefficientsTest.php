<?php

declare(strict_types=1);

use App\Modules\Pedagogie\Models\BulletinVersion;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\Note;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Services\BulletinService;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Niveau;

it('pondère les évaluations et utilise le coefficient annuel de la matière', function (): void {
    $niveau = Niveau::create(['nom' => 'Terminale', 'code' => 'TLE', 'ordre' => 12]);
    $annee = AnneeScolaire::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'statut' => 'active']);
    $classe = Classe::create(['nom' => 'Terminale D', 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id, 'capacite_max' => 40]);
    $matiere = Matiere::create(['nom' => 'Mathématiques', 'code' => 'MATH-COEF', 'coefficient' => 1, 'actif' => true]);
    OffrePedagogique::create(['matiere_id' => $matiere->id, 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id, 'coefficient_matiere' => 5, 'actif' => true]);

    $devoir = Evaluation::create(['titre' => 'Devoir', 'matiere_id' => $matiere->id, 'classe_id' => $classe->id, 'annee_scolaire_id' => $annee->id, 'date_evaluation' => now()->subDays(2), 'bareme' => 20, 'coefficient_evaluation' => 1, 'statut' => 'valide']);
    $composition = Evaluation::create(['titre' => 'Composition', 'matiere_id' => $matiere->id, 'classe_id' => $classe->id, 'annee_scolaire_id' => $annee->id, 'date_evaluation' => now()->subDay(), 'bareme' => 20, 'coefficient_evaluation' => 3, 'statut' => 'valide']);
    Note::create(['evaluation_id' => $devoir->id, 'eleve_id' => 99, 'valeur' => 10, 'premiere_saisie_at' => now()]);
    Note::create(['evaluation_id' => $composition->id, 'eleve_id' => 99, 'valeur' => 20, 'premiere_saisie_at' => now()]);

    $service = new BulletinService(classeService: app(ClasseServiceInterface::class));
    $bulletin = $service->genererPourEleve(99, $classe->id, $annee->id);

    expect((float) $bulletin->moyenne_generale)->toBe(17.5)
        ->and((float) $bulletin->matieres->first()->moyenne)->toBe(17.5)
        ->and((float) $bulletin->matieres->first()->coefficient)->toBe(5.0);
});

it('exclut une absence du calcul et applique le workflow de publication', function (): void {
    $niveau = Niveau::create(['nom' => 'Première', 'code' => '1ERE', 'ordre' => 11]);
    $annee = AnneeScolaire::create(['libelle' => '2027-2028', 'date_debut' => '2027-09-01', 'date_fin' => '2028-07-31', 'statut' => 'active']);
    $classe = Classe::create(['nom' => 'Première A', 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id, 'capacite_max' => 40]);
    $matiere = Matiere::create(['nom' => 'Français', 'code' => 'FR-ABS', 'coefficient' => 2, 'coefficient_defaut' => 2, 'actif' => true]);
    $devoir = Evaluation::create(['titre' => 'Devoir', 'matiere_id' => $matiere->id, 'classe_id' => $classe->id, 'annee_scolaire_id' => $annee->id, 'date_evaluation' => now()->subDays(2), 'bareme' => 20, 'coefficient_evaluation' => 1, 'statut' => 'valide']);
    $composition = Evaluation::create(['titre' => 'Composition', 'matiere_id' => $matiere->id, 'classe_id' => $classe->id, 'annee_scolaire_id' => $annee->id, 'date_evaluation' => now()->subDay(), 'bareme' => 20, 'coefficient_evaluation' => 3, 'statut' => 'valide']);
    Note::create(['evaluation_id' => $devoir->id, 'eleve_id' => 101, 'valeur' => 12, 'premiere_saisie_at' => now()]);
    Note::create(['evaluation_id' => $composition->id, 'eleve_id' => 101, 'valeur' => null, 'absent' => true, 'premiere_saisie_at' => now()]);

    $service = new BulletinService(classeService: app(ClasseServiceInterface::class));
    $bulletin = $service->genererPourEleve(101, $classe->id, $annee->id);

    expect((float) $bulletin->moyenne_generale)->toBe(12.0)
        ->and($bulletin->statut)->toBe('calcule');

    foreach (['soumis', 'valide', 'publie'] as $statut) {
        $bulletin = $service->changerStatut($bulletin->id, $statut);
    }

    expect($bulletin->statut)->toBe('publie')
        ->and(BulletinVersion::where('bulletin_id', $bulletin->id)->count())->toBe(1)
        ->and(fn () => $service->genererPourEleve(101, $classe->id, $annee->id))->toThrow(RuntimeException::class);
});
