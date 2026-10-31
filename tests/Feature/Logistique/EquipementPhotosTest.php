<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Logistique\Contracts\EquipementServiceInterface;
use App\Modules\Logistique\Models\Equipement;
use App\Modules\Logistique\Models\EquipementPhoto;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->service ??= app(EquipementServiceInterface::class);
    Storage::fake('documents');
    $user = User::factory()->create(['statut' => 'actif']);
    $this->actingAs($user);
});

it('gere un equipement sans photo', function () {
    $equipement = Equipement::create([
        'nom' => 'Tableau blanc',
        'numero_identification' => 'EQ-PH-01',
        'date_acquisition' => now(),
    ]);

    expect($equipement->photos)->toHaveCount(0)
        ->and($equipement->photoPrincipale)->toBeNull();
});

it('ajoute une photo a un equipement et conserve la reference GED sans stocker de binaire', function () {
    $equipement = Equipement::create([
        'nom' => 'Chaise bureau',
        'numero_identification' => 'EQ-PH-02',
        'date_acquisition' => now(),
    ]);

    $file = UploadedFile::fake()->image('chaise.jpg');

    $photo = $this->service->ajouterPhoto($equipement->id, $file, 'principale', 'Vue de face', true);

    expect($photo)->toBeInstanceOf(EquipementPhoto::class)
        ->and($photo->document_id)->not->toBeNull()
        ->and($photo->principal)->toBeTrue()
        ->and($equipement->fresh()->photos)->toHaveCount(1)
        ->and($equipement->fresh()->photoPrincipale->id)->toBe($photo->id);

    // Vérifie qu'aucune donnée binaire n'est stockée dans la table equipement_photos
    $attributes = $photo->getAttributes();
    foreach ($attributes as $key => $value) {
        expect($key)->not->toBe('data')
            ->not->toBe('file')
            ->not->toBe('binary');
    }
});

it('garantit une seule photo principale a la fois et permet son remplacement', function () {
    $equipement = Equipement::create([
        'nom' => 'Armoire',
        'numero_identification' => 'EQ-PH-03',
        'date_acquisition' => now(),
    ]);

    $file1 = UploadedFile::fake()->image('armoire1.jpg');
    $file2 = UploadedFile::fake()->image('armoire2.jpg');

    $photo1 = $this->service->ajouterPhoto($equipement->id, $file1, 'principale', 'Première photo', true);
    $photo2 = $this->service->ajouterPhoto($equipement->id, $file2, 'etat', 'Deuxième photo', true);

    expect($photo1->fresh()->principal)->toBeFalse()
        ->and($photo2->fresh()->principal)->toBeTrue()
        ->and($equipement->fresh()->photoPrincipale->id)->toBe($photo2->id);

    // Redéfinir la première comme principale
    $this->service->definirPhotoPrincipale($equipement->id, $photo1->id);

    expect($photo1->fresh()->principal)->toBeTrue()
        ->and($photo2->fresh()->principal)->toBeFalse()
        ->and($equipement->fresh()->photoPrincipale->id)->toBe($photo1->id);
});

it('retire une photo sans supprimer le document GED sous-jacent', function () {
    $equipement = Equipement::create([
        'nom' => 'Projecteur',
        'numero_identification' => 'EQ-PH-04',
        'date_acquisition' => now(),
    ]);

    $file = UploadedFile::fake()->image('projecteur.jpg');
    $photo = $this->service->ajouterPhoto($equipement->id, $file, 'principale', 'Photo projecteur', true);
    $documentId = $photo->document_id;

    $this->service->retirerPhoto($photo->id);

    expect($equipement->fresh()->photos)->toHaveCount(0);

    // Le document GED existe toujours via le DocumentServiceContract
    $docService = app(DocumentServiceContract::class);
    $url = $docService->getUrlTelechargement($documentId);
    expect($url)->not->toBeNull();
});

it('leve une exception si l equipement n existe pas lors de l ajout d une photo', function () {
    $file = UploadedFile::fake()->image('test.jpg');

    expect(fn () => $this->service->ajouterPhoto(99999, $file))
        ->toThrow(ModelNotFoundException::class);
});
