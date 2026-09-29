<?php

use App\Modules\Logistique\Contracts\BibliothequeServiceInterface;
use App\Modules\Logistique\Exceptions\ExemplaireIndisponibleException;
use App\Modules\Logistique\Models\ExemplaireLivre;
use App\Modules\Logistique\Models\Livre;
use App\Modules\Scolarite\Models\Eleve;

beforeEach(function () {
    $this->service ??= app(BibliothequeServiceInterface::class);
    $this->livre = Livre::create(['titre' => 'Le Petit Prince', 'auteur' => 'Saint-Exupéry']);
    $this->exemplaire = ExemplaireLivre::create([
        'livre_id' => $this->livre->id, 'code_exemplaire' => 'EX-001', 'disponible' => true,
    ]);
    // TODO: remplacer par une vraie factory Eleve une fois disponible.
    $this->emprunteur = Eleve::create(['nom' => 'Test', 'prenom' => 'Élève']);
});

it('emprunte un exemplaire disponible', function () {
    $emprunt = $this->service->emprunter($this->exemplaire->id, $this->emprunteur);

    expect($emprunt)->not->toBeNull()
        ->and($this->exemplaire->fresh()->disponible)->toBeFalse();
});

it('refuse d\'emprunter un exemplaire déjà indisponible', function () {
    $this->exemplaire->update(['disponible' => false]);

    expect(fn () => $this->service->emprunter($this->exemplaire->id, $this->emprunteur))
        ->toThrow(ExemplaireIndisponibleException::class);
});

it('retourne un exemplaire et le rend de nouveau disponible', function () {
    $emprunt = $this->service->emprunter($this->exemplaire->id, $this->emprunteur);

    $this->service->retourner($emprunt->id);

    expect($this->exemplaire->fresh()->disponible)->toBeTrue();
});

it('applique une pénalité en cas de retour en retard', function () {
    $emprunt = $this->service->emprunter($this->exemplaire->id, $this->emprunteur, dureeJours: 14);
    $emprunt->update(['date_retour_prevue' => now()->subDays(5)]);

    $retour = $this->service->retourner($emprunt->id);

    expect((float) $retour->penalite)->toBeGreaterThan(0);
});

it('réserve un livre déjà emprunté', function () {
    $reservation = $this->service->reserver($this->livre->id, $this->emprunteur);

    expect($reservation)->not->toBeNull()
        ->and($reservation->statut)->toBe('active');
});

it('retourne l\'historique des emprunts d\'un emprunteur', function () {
    $this->service->emprunter($this->exemplaire->id, $this->emprunteur);

    $historique = $this->service->getHistoriqueEmprunteur($this->emprunteur);

    expect($historique)->toHaveCount(1);
});
