<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Socle\Models\Fonctionnalite;
use App\Modules\Socle\Models\Groupe;

it('keeps existing groups and memberships as official access groups', function (): void {
    $member = User::factory()->create(['statut' => 'actif']);
    $group = Groupe::query()->create(['nom' => 'Direction pédagogique']);
    $group->membres()->attach($member->id);

    expect($group->isAccessGroup())->toBeTrue()
        ->and($group->accessMembers()->whereKey($member->id)->exists())->toBeTrue()
        ->and($group->fresh()->membres()->pluck('users.id')->all())->toBe([$member->id]);
});

it('adapts catalogue_fonctionnalites as the single feature catalogue', function (): void {
    $parent = Fonctionnalite::query()->create([
        'code' => 'socle.acces_test',
        'nom' => 'Administration',
        'categorie' => 'Administration',
        'module_technique' => 'socle',
        'ordre' => 1,
        'actif' => true,
    ]);
    $feature = Fonctionnalite::query()->create([
        'code' => 'socle.acces_test_utilisateurs',
        'nom' => 'Utilisateurs',
        'categorie' => 'Administration',
        'module_technique' => 'socle',
        'parent_id' => $parent->id,
        'ordre' => 2,
        'actif' => true,
    ]);

    expect($feature->id)->toBeInt()
        ->and($feature->module)->toBe('socle')
        ->and($feature->name)->toBe('Utilisateurs')
        ->and($feature->is_active)->toBeTrue()
        ->and($feature->parent->id)->toBe($parent->id)
        ->and($parent->children->pluck('id')->all())->toBe([$feature->id]);
});

it('rejects a feature code outside the module.resource convention', function (): void {
    expect(fn () => Fonctionnalite::query()->create([
        'code' => 'invalid_code',
        'nom' => 'Invalide',
        'categorie' => 'Test',
    ]))->toThrow(InvalidArgumentException::class);
});
