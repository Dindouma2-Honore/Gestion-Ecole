<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use RuntimeException;

/**
 * Double de test pour EleveServiceInterface : le module Scolarité n'a pas
 * encore d'implémentation concrète, donc les modules qui en dépendent
 * (Finances...) se testent contre ce fake plutôt que contre du vrai.
 */
class FakeEleveService implements EleveServiceInterface
{
    /** @param array<int, array{nom: string, prenom: string, classe_id: int, niveau_id: int}> $eleves */
    public function __construct(private array $eleves = []) {}

    public function existe(int $eleveId): bool
    {
        return isset($this->eleves[$eleveId]);
    }

    public function getEleve(int $eleveId): array
    {
        if (! $this->existe($eleveId)) {
            throw new RuntimeException("Élève #{$eleveId} inconnu du fake.");
        }

        return [
            'id' => $eleveId,
            'nom' => $this->eleves[$eleveId]['nom'],
            'prenom' => $this->eleves[$eleveId]['prenom'],
            'classe_id' => $this->eleves[$eleveId]['classe_id'],
        ];
    }

    public function getNiveauId(int $eleveId): int
    {
        if (! $this->existe($eleveId)) {
            throw new RuntimeException("Élève #{$eleveId} inconnu du fake.");
        }

        return $this->eleves[$eleveId]['niveau_id'];
    }

    public function getElevesActifsIds(?int $niveauId = null): array
    {
        return collect($this->eleves)
            ->when($niveauId !== null, fn ($eleves) => $eleves->where('niveau_id', $niveauId))
            ->keys()
            ->all();
    }

    public function getElevesPourSituationFinanciere(?int $niveauId = null, ?int $classeId = null, ?string $sexe = null): array
    {
        return collect($this->eleves)
            ->when($niveauId !== null, fn ($eleves) => $eleves->where('niveau_id', $niveauId))
            ->when($classeId !== null, fn ($eleves) => $eleves->where('classe_id', $classeId))
            ->map(fn (array $eleve, int $id): array => [
                'id' => $id, 'nom' => $eleve['nom'], 'prenom' => $eleve['prenom'], 'sexe' => $eleve['sexe'] ?? null,
                'classe_id' => $eleve['classe_id'], 'classe' => $eleve['classe'] ?? 'Classe #'.$eleve['classe_id'],
                'niveau_id' => $eleve['niveau_id'],
            ])->values()->all();
    }

    public function creer(array $donnees): array
    {
        $id = $this->eleves === [] ? 1 : max(array_keys($this->eleves)) + 1;
        $this->eleves[$id] = [
            'nom' => $donnees['nom'],
            'prenom' => $donnees['prenom'],
            'classe_id' => (int) ($donnees['classe_id'] ?? 0),
            'niveau_id' => (int) ($donnees['niveau_id'] ?? 0),
        ];

        return ['id' => $id, ...$this->eleves[$id]];
    }

    /**
     * Génère un matricule permanent unique. Le fake se contente d'un
     * compteur incrémental déterministe — suffisant pour les tests, qui
     * n'ont besoin que d'unicité, pas du vrai format de production.
     */
    public function genererMatricule(): string
    {
        $numero = count($this->eleves) + 1;

        return 'ELV-'.str_pad((string) $numero, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Retourne les élèves actifs d'une classe précise — utilisé pour peupler
     * les sélecteurs (Évaluation, etc.).
     *
     * @return array<int, array{id: int, nom: string, prenom: string}>
     */
    public function getElevesParClasse(int $classeId): array
    {
        return collect($this->eleves)
            ->filter(fn (array $eleve): bool => $eleve['classe_id'] === $classeId)
            ->map(fn (array $eleve, int $id): array => [
                'id' => $id,
                'nom' => $eleve['nom'],
                'prenom' => $eleve['prenom'],
            ])
            ->values()
            ->all();
    }
}
