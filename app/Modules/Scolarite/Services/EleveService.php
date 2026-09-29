<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Exceptions\EleveIntrouvableException;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Socle\Contracts\ParametrageServiceContract;

class EleveService implements EleveServiceInterface
{
    public function __construct(
        private readonly ParametrageServiceContract $parametrageService,
    ) {}

    public function existe(int $eleveId): bool
    {
        return Eleve::whereKey($eleveId)->exists();
    }

    public function getEleve(int $eleveId): array
    {
        $eleve = Eleve::with('inscriptions')->find($eleveId);

        if (! $eleve) {
            throw EleveIntrouvableException::pourId($eleveId);
        }

        $inscriptionActive = $eleve->inscriptions->firstWhere('statut', 'validee');

        return [
            'id' => $eleve->id,
            'nom' => $eleve->nom,
            'prenom' => $eleve->prenom,
            'classe_id' => $inscriptionActive?->classe_id,
        ];
    }

    public function creer(array $donnees): array
    {
        $eleve = Eleve::create([
            'nom' => $donnees['nom'],
            'prenom' => $donnees['prenom'],
            'date_naissance' => $donnees['date_naissance'] ?? null,
            'sexe' => $donnees['sexe'] ?? null,
            'photo' => $donnees['photo'] ?? null,
            'statut' => 'prospect',
        ]);

        return $eleve->toArray();
    }

    public function genererMatricule(): string
    {
        return $this->parametrageService->genererNumero('matricule_eleve');
    }

    public function getNiveauId(int $eleveId): int
    {
        $eleve = Eleve::with(['inscriptions' => fn ($query) => $query
            ->whereIn('statut', ['validee', 'en_attente_versement'])
            ->latest('date_inscription')
            ->with('classe')])
            ->find($eleveId);

        if (! $eleve) {
            throw EleveIntrouvableException::pourId($eleveId);
        }

        $niveauId = $eleve->inscriptions->first()?->classe?->niveau_id;

        if ($niveauId === null) {
            throw EleveIntrouvableException::pourId($eleveId);
        }

        return (int) $niveauId;
    }

    public function getElevesActifsIds(?int $niveauId = null): array
    {
        return Eleve::query()
            ->where('statut', 'actif')
            ->when($niveauId !== null, fn ($query) => $query->whereHas(
                'inscriptions.classe',
                fn ($classe) => $classe->where('niveau_id', $niveauId),
            ))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function getElevesPourSituationFinanciere(?int $niveauId = null, ?int $classeId = null, ?string $sexe = null): array
    {
        return Eleve::query()
            ->where('statut', 'actif')
            ->when($sexe, fn ($query) => $query->where('sexe', $sexe))
            ->whereHas('inscriptions', fn ($query) => $query
                ->where('statut', 'validee')
                ->when($classeId, fn ($inscriptions) => $inscriptions->where('classe_id', $classeId))
                ->when($niveauId, fn ($inscriptions) => $inscriptions->whereHas('classe', fn ($classes) => $classes->where('niveau_id', $niveauId))))
            ->with(['parentsTuteurs', 'inscriptions' => fn ($query) => $query->where('statut', 'validee')->latest('id')->with('classe')])
            ->orderBy('nom')->orderBy('prenom')->get()
            ->map(function (Eleve $eleve): array {
                $inscription = $eleve->inscriptions->first();

                return [
                    'id' => $eleve->id, 'nom' => $eleve->nom, 'prenom' => $eleve->prenom, 'sexe' => $eleve->sexe,
                    'classe_id' => (int) $inscription->classe_id, 'classe' => $inscription->classe->nom,
                    'niveau_id' => (int) $inscription->classe->niveau_id,
                    'matricule' => $eleve->matricule_permanent,
                    'niveau' => (string) $inscription->classe->niveau_id,
                    'telephone_parent' => $eleve->parentsTuteurs->firstWhere('pivot.responsable_paiement', true)?->telephone ?? $eleve->parentsTuteurs->first()?->telephone ?? '',
                ];
            })->all();
    }

    public function getElevesParClasse(int $classeId): array
    {
        return Eleve::whereHas('inscriptions', fn ($q) => $q
            ->where('classe_id', $classeId)
            ->whereIn('statut', ['en_cours', 'validee']))
            ->get()
            ->map(fn (Eleve $e) => ['id' => $e->id, 'nom' => $e->nom, 'prenom' => $e->prenom])
            ->all();
    }
}
