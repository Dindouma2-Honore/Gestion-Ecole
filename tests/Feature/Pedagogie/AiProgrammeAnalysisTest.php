<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Enums\StatutProgrammeEnum;
use App\Modules\Pedagogie\Jobs\AnalyzeProgrammeJob;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeAnalyse;
use App\Modules\Pedagogie\Services\AiAnalysisService;
use App\Modules\Pedagogie\Services\DocumentExtractorService;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->annee = AnneeScolaire::create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'statut' => 'active',
    ]);

    $this->matiere = Matiere::create([
        'nom' => 'Physique-Chimie',
        'code' => 'PHY',
    ]);

    $this->user = User::factory()->create([
        'name' => 'Professeur Dupont',
        'email' => 'dupont@ambassadors.test',
    ]);

    $this->service = app(ProgrammeServiceInterface::class);
});

it('execute avec succes l’analyse intelligente d’un programme et genere les chapitres', function (): void {
    // Création d'un document source textuel
    Storage::fake('documents');
    $fichierContenu = "Programme de Physique-Chimie 4ème\n\n"
        . "Chapitre 1 : Les mélanges et corps purs\n"
        . "Objectif : Identifier les différents états de la matière.\n"
        . "- Leçon 1 : Mélanges homogènes et hétérogènes (2h)\n"
        . "- Leçon 2 : Séparation des mélanges (2h)\n\n"
        . "Chapitre 2 : La vitesse et les mouvements\n"
        . "Objectif : Calculer une vitesse moyenne.\n"
        . "- Leçon 1 : Notion de mouvement et trajectoire (2h)\n";

    $path = 'programmes/test_physique.txt';
    Storage::disk('documents')->put($path, $fichierContenu);

    $document = Document::create([
        'nom' => 'programme_physique.txt',
        'categorie' => 'programme_source',
        'fichier_path' => $path,
        'mime_type' => 'text/plain',
        'taille' => strlen($fichierContenu),
        'documentable_type' => Programme::class,
        'documentable_id' => 1,
        'created_by' => $this->user->id,
    ]);

    $programme = Programme::create([
        'matiere_id' => $this->matiere->id,
        'annee_scolaire_id' => $this->annee->id,
        'niveau_id' => 8,
        'source' => 'enseignant',
        'enseignant_id' => $this->user->id,
        'titre' => 'Programme Physique 4ème',
        'statut' => StatutProgrammeEnum::ANALYSE_EN_ATTENTE->value,
        'document_source_id' => $document->id,
    ]);

    $misAJour = $this->service->lancerAnalyseIntelligente($programme->id, $this->user->id);

    expect($misAJour->statut)->toBe(StatutProgrammeEnum::A_VALIDER->value);
    expect($misAJour->chapitres()->count())->toBeGreaterThanOrEqual(2);

    $analyse = ProgrammeAnalyse::where('programme_id', $programme->id)->first();
    expect($analyse)->not->toBeNull();
    expect($analyse->status)->toBe('termine');
    expect($analyse->analysis_result)->toBeArray();
    expect($analyse->analysis_result['objectifs'] ?? null)->toBeArray();
});

it('prend en charge une reponse JSON structuree depuis un provider IA externe', function (): void {
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'program_name' => 'Physique Avancée',
                            'description' => 'Cours complet de mécanique et optique.',
                            'objectifs' => ['Comprendre les lois de Newton', 'Manipuler des lasers'],
                            'duration' => '40 heures',
                            'target_audience' => 'Élèves de 3ème',
                            'prerequisites' => ['Mathématiques 4ème'],
                            'skills' => ['Raisonnement scientifique', 'Expérimentation'],
                            'teaching_methods' => ['TP', 'Cours magistral'],
                            'evaluation_methods' => ['Devoir sur table', 'Évaluation de TP'],
                            'career_opportunities' => ['Sciences de l’ingénieur'],
                            'level' => '3ème',
                            'missing_information' => ['Pas de date d’examen final'],
                            'inconsistencies' => [],
                            'duplicates' => [],
                            'summary' => 'Programme solide et bien équilibré.',
                            'modules' => [
                                [
                                    'title' => 'Module 1 : Mécanique classique',
                                    'description' => 'Forces et mouvements',
                                    'hours' => 10,
                                    'objectives' => 'Forces fondamentales',
                                    'lessons' => [
                                        ['title' => 'Loi de Newton 1', 'hours' => 2, 'type' => 'cours', 'objectives' => 'Énoncer la loi'],
                                        ['title' => 'Loi de Newton 2', 'hours' => 3, 'type' => 'tp', 'objectives' => 'Mesurer l’accélération'],
                                    ],
                                ],
                            ],
                        ]),
                    ],
                ],
            ],
            'usage' => [
                'total_tokens' => 350,
            ],
        ], 200),
    ]);

    config([
        'ai.provider' => 'openai',
        'ai.api_key' => 'sk-test-mock-key-12345',
        'ai.openai.api_key' => 'sk-test-mock-key-12345',
    ]);

    Storage::fake('documents');
    $fichierContenu = "Document brut de physique.";
    $path = 'programmes/physique_ai.txt';
    Storage::disk('documents')->put($path, $fichierContenu);

    $document = Document::create([
        'nom' => 'physique_ai.txt',
        'categorie' => 'programme_source',
        'fichier_path' => $path,
        'mime_type' => 'text/plain',
        'taille' => strlen($fichierContenu),
        'documentable_type' => Programme::class,
        'documentable_id' => 2,
        'created_by' => $this->user->id,
    ]);

    $programme = Programme::create([
        'matiere_id' => $this->matiere->id,
        'annee_scolaire_id' => $this->annee->id,
        'niveau_id' => 9,
        'source' => 'enseignant',
        'enseignant_id' => $this->user->id,
        'titre' => 'Programme Physique Mock IA',
        'statut' => StatutProgrammeEnum::ANALYSE_EN_ATTENTE->value,
        'document_source_id' => $document->id,
    ]);

    $misAJour = $this->service->lancerAnalyseIntelligente($programme->id, $this->user->id);

    expect($misAJour->statut)->toBe(StatutProgrammeEnum::A_VALIDER->value);

    $analyse = ProgrammeAnalyse::where('programme_id', $programme->id)->latest('id')->first();
    expect($analyse->status)->toBe('termine');
    expect($analyse->ai_provider)->toBe('openai');
    expect($analyse->tokens_used)->toBe(350);
    expect($analyse->analysis_result['skills'])->toContain('Raisonnement scientifique');
    expect($analyse->analysis_result['missing_information'])->toContain('Pas de date d’examen final');
});

it('enregistre l’echec et met a jour le statut en cas de document introuvable ou illisible', function (): void {
    $programme = Programme::create([
        'matiere_id' => $this->matiere->id,
        'annee_scolaire_id' => $this->annee->id,
        'niveau_id' => 9,
        'source' => 'enseignant',
        'enseignant_id' => $this->user->id,
        'titre' => 'Programme Sans Fichier',
        'statut' => StatutProgrammeEnum::ANALYSE_EN_ATTENTE->value,
        'document_source_id' => null,
    ]);

    $misAJour = $this->service->lancerAnalyseIntelligente($programme->id, $this->user->id);

    expect($misAJour->statut)->toBe(StatutProgrammeEnum::ECHEC_ANALYSE->value);
    expect($misAJour->erreur_analyse)->not->toBeNull();

    $analyse = ProgrammeAnalyse::where('programme_id', $programme->id)->latest('id')->first();
    expect($analyse)->not->toBeNull();
    expect($analyse->status)->toBe('echec');
    expect($analyse->error_message)->not->toBeNull();
});

it('permet de declencher l’analyse via la route HTTP POST', function (): void {
    Storage::fake('documents');
    $fichierContenu = "Chapitre 1 : Introduction (2h)\nLeçon 1 : Bases";
    $path = 'programmes/http_test.txt';
    Storage::disk('documents')->put($path, $fichierContenu);

    $document = Document::create([
        'nom' => 'http_test.txt',
        'categorie' => 'programme_source',
        'fichier_path' => $path,
        'mime_type' => 'text/plain',
        'taille' => strlen($fichierContenu),
        'documentable_type' => Programme::class,
        'documentable_id' => 3,
        'created_by' => $this->user->id,
    ]);

    $programme = Programme::create([
        'matiere_id' => $this->matiere->id,
        'annee_scolaire_id' => $this->annee->id,
        'niveau_id' => 7,
        'source' => 'enseignant',
        'enseignant_id' => $this->user->id,
        'titre' => 'Programme HTTP Test',
        'statut' => StatutProgrammeEnum::ANALYSE_EN_ATTENTE->value,
        'document_source_id' => $document->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('pedagogie.programmes.analyser', ['programme' => $programme->id]));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'programme' => [
                'id' => $programme->id,
                'statut' => StatutProgrammeEnum::A_VALIDER->value,
            ],
        ]);
});
