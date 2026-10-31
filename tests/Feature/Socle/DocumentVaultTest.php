<?php

use App\Models\User;
use App\Modules\Socle\Contracts\DocumentVaultContract;
use App\Modules\Socle\Data\DocumentUploadData;
use App\Modules\Socle\Models\Document;
use Illuminate\Support\Facades\Storage;

function vaultUpload(): DocumentUploadData
{
    $p = tempnam(sys_get_temp_dir(), 'dv');
    file_put_contents($p, 'content');

    return new DocumentUploadData($p, 'a.pdf', 'application/pdf', 7, 'test', User::class, 1);
}
it('stores privately', function () {
    Storage::fake('documents');
    $u = User::factory()->create();
    $d = app(DocumentVaultContract::class)->store(vaultUpload(), $u->id);
    expect($d->confidentialityLevel)->toBe('interne');
});
it('retrieves a reference', function () {
    Storage::fake('documents');
    $u = User::factory()->create();
    $d = app(DocumentVaultContract::class)->store(vaultUpload(), $u->id);
    expect(app(DocumentVaultContract::class)->document($d->id)?->id)->toBe($d->id);
});
it('versions documents', function () {
    Storage::fake('documents');
    $u = User::factory()->create();
    $d = app(DocumentVaultContract::class)->store(vaultUpload(), $u->id);
    app(DocumentVaultContract::class)->createVersion($d->id, vaultUpload(), $u->id);
    expect(app(DocumentVaultContract::class)->versionsOf($d->id))->toHaveCount(1);
});
it('protects temporary access', function () {
    Storage::fake('documents');
    $u = User::factory()->create(['statut' => 'actif']);
    $d = app(DocumentVaultContract::class)->store(vaultUpload(), $u->id);
    expect(app(DocumentVaultContract::class)->temporaryDownloadUrl($u->id, $d->id))->not->toBeNull();
});
it('keeps legacy documents readable', function () {
    $user = User::factory()->create();
    $d = Document::create(['nom' => 'legacy', 'categorie' => 'x', 'fichier_path' => 'x', 'mime_type' => 'application/pdf', 'taille' => 1, 'documentable_type' => User::class, 'documentable_id' => $user->id, 'niveau_confidentialite' => 'interne', 'created_by' => $user->id]);
    expect(app(DocumentVaultContract::class)->document($d->id)?->name)->toBe('legacy');
});
