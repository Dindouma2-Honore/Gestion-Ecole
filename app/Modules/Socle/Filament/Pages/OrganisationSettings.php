<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use App\Modules\RH\Contracts\PosteReadContract;
use App\Modules\RH\Models\Employe;
use Illuminate\Support\Collection;

class OrganisationSettings extends AdministrationSettings
{
    protected static ?string $slug = 'administration/parametres/organisation';

    protected static ?string $title = 'Organisation & Schéma Hiérarchique';

    protected const CATEGORY = 'organisation';

    protected string $view = 'socle::filament.pages.organisation-settings';

    public function getSubheading(): ?string
    {
        return 'Schéma hiérarchique et organigramme de l’établissement selon les fonctions RH et le personnel en poste.';
    }

    // Filtres & Mode de vue (Consultation uniquement)
    public string $search = '';

    public string $selectedDepartement = 'all';

    public string $selectedStatut = 'all';

    public string $viewMode = 'organigramme'; // 'organigramme' ou 'tableau'

    public bool $showUnclassified = false;

    public array $collapsedNodes = [];

    public int $zoom = 95;

    public function zoomIn(): void
    {
        $this->zoom = min(130, $this->zoom + 10);
    }

    public function zoomOut(): void
    {
        $this->zoom = max(60, $this->zoom - 10);
    }

    public function resetZoom(): void
    {
        $this->zoom = 95;
    }

    public function toggleNode(int $nodeId): void
    {
        if (in_array($nodeId, $this->collapsedNodes, true)) {
            $this->collapsedNodes = array_values(array_diff($this->collapsedNodes, [$nodeId]));
        } else {
            $this->collapsedNodes[] = $nodeId;
        }
    }

    public function expandAll(): void
    {
        $this->collapsedNodes = [];
    }

    public function collapseAll(): void
    {
        $this->collapsedNodes = app(PosteReadContract::class)->readAll()->pluck('id')->all();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->selectedDepartement = 'all';
        $this->selectedStatut = 'all';
        $this->zoom = 95;
    }

    // Propriétés calculées
    public function getStatsProperty(): array
    {
        $postes = app(PosteReadContract::class)->readAll()
            ->filter(fn ($p): bool => ! str_contains((string) $p->nom, 'FACILG') || $p->id !== 57);

        $totalFonctions = $postes->count();
        $fonctionsPourvues = $postes->filter(fn ($p): bool => ($p->employes_count ?? 0) > 0)->count();
        $fonctionsVacantes = $totalFonctions - $fonctionsPourvues;
        $totalCollaborateurs = (int) $postes->sum('employes_count');
        $tauxCouverture = $totalFonctions > 0 ? (int) round(($fonctionsPourvues / $totalFonctions) * 100) : 0;

        return [
            'totalFonctions' => $totalFonctions,
            'fonctionsPourvues' => $fonctionsPourvues,
            'fonctionsVacantes' => $fonctionsVacantes,
            'totalCollaborateurs' => $totalCollaborateurs,
            'tauxCouverture' => $tauxCouverture,
        ];
    }

    public function getDepartementsListProperty(): array
    {
        return app(PosteReadContract::class)->readByDepartement(null)
            ->pluck('departement')
            ->filter(fn ($departement): bool => $departement !== null && $departement !== '')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    public function getUnclassifiedEmployeesCountProperty(): int
    {
        return Employe::where('statut', 'actif')
            ->where(function ($q): void {
                $q->whereNull('poste_administratif_id')
                    ->orWhere('poste_administratif_id', 57);
            })
            ->count();
    }

    public function getAllPostesProperty(): Collection
    {
        return $this->postesFiltres();
    }

    public function getHierarchyTreeProperty(): array
    {
        return $this->buildTree($this->postesFiltres());
    }

    private function postesFiltres(): Collection
    {
        $postes = app(PosteReadContract::class)->organigramme();

        if (! $this->showUnclassified) {
            $postes = $postes->filter(fn ($p): bool => $p->id !== 57);
        }

        if ($this->selectedDepartement !== 'all') {
            $postes = $postes->filter(fn ($p): bool => $p->departement === $this->selectedDepartement);
        }

        if ($this->selectedStatut === 'pourvu') {
            $postes = $postes->filter(fn ($p): bool => $p->employes->isNotEmpty());
        } elseif ($this->selectedStatut === 'vacant') {
            $postes = $postes->filter(fn ($p): bool => $p->employes->isEmpty());
        }

        if (trim($this->search) !== '') {
            $term = mb_strtolower(trim($this->search));
            $postes = $postes->filter(function ($p) use ($term): bool {
                $nameMatches = str_contains(mb_strtolower((string) $p->nom), $term);
                $deptMatches = str_contains(mb_strtolower((string) ($p->departement ?? '')), $term);
                $staffMatches = $p->employes->contains(fn ($emp): bool => str_contains(mb_strtolower((string) $emp->nom), $term)
                    || str_contains(mb_strtolower((string) $emp->prenom), $term)
                    || str_contains(mb_strtolower((string) $emp->matricule), $term));

                return $nameMatches || $deptMatches || $staffMatches;
            });
        }

        return $postes->values();
    }

    private function buildTree(Collection $elements, ?int $parentId = null): array
    {
        $branch = [];
        $candidates = $elements->where('parent_id', $parentId);

        foreach ($candidates as $element) {
            $children = $this->buildTree($elements, $element->id);

            // Filtrage par département ou statut si activé
            $matchesFilter = true;
            if ($this->selectedDepartement !== 'all' && $element->departement !== $this->selectedDepartement) {
                $matchesFilter = false;
            }
            if ($this->selectedStatut === 'pourvu' && $element->employes->isEmpty()) {
                $matchesFilter = false;
            }
            if ($this->selectedStatut === 'vacant' && $element->employes->isNotEmpty()) {
                $matchesFilter = false;
            }

            // Filtrage par texte de recherche
            if (trim($this->search) !== '') {
                $term = mb_strtolower(trim($this->search));
                $nameMatches = str_contains(mb_strtolower((string) $element->nom), $term);
                $deptMatches = str_contains(mb_strtolower((string) ($element->departement ?? '')), $term);
                $staffMatches = $element->employes->contains(function ($emp) use ($term): bool {
                    return str_contains(mb_strtolower((string) $emp->nom), $term)
                        || str_contains(mb_strtolower((string) $emp->prenom), $term)
                        || str_contains(mb_strtolower((string) $emp->matricule), $term);
                });

                if (! $nameMatches && ! $deptMatches && ! $staffMatches && empty($children)) {
                    $matchesFilter = false;
                }
            }

            if ($matchesFilter || ! empty($children)) {
                $branch[] = [
                    'poste' => $element,
                    'children' => $children,
                ];
            }
        }

        // Si aucun root (parent_id == null) n'est trouvé pour ce filtrage mais qu'il y a des éléments
        if ($parentId === null && empty($branch) && $elements->isNotEmpty()) {
            $minNiveau = $elements->min('niveau_hierarchique') ?? 1;
            $topLevel = $elements->where('niveau_hierarchique', $minNiveau);
            foreach ($topLevel as $element) {
                $branch[] = [
                    'poste' => $element,
                    'children' => $this->buildTree($elements, $element->id),
                ];
            }
        }

        return $branch;
    }
}
