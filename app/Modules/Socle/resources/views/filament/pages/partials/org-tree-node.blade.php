@php
    $poste = $node['poste'];
    $children = $node['children'] ?? [];
    $hasChildren = !empty($children);
    $isCollapsed = in_array($poste->id, $this->collapsedNodes, true);

    $lvlClass = match ($poste->niveau_hierarchique) {
        1 => 'amb-lvl-1',
        2 => 'amb-lvl-2',
        3 => 'amb-lvl-3',
        4 => 'amb-lvl-4',
        default => 'amb-lvl-5',
    };
@endphp

<div class="{{ $isRoot ? 'amb-tree-node-wrapper' : 'amb-tree-child-wrapper' }} {{ $hasChildren && !$isCollapsed ? 'has-children' : '' }}">
    <!-- Carte du nœud fonctionnel (Consultation pure) -->
    <div class="amb-tree-node-card">
        <!-- En-tête : Niveau hiérarchique & Pôle -->
        <div class="amb-node-header">
            <span class="amb-node-level {{ $lvlClass }}">N{{ $poste->niveau_hierarchique }}</span>
            <span class="amb-node-dept" title="{{ $poste->departement ?? 'Non assigné' }}">
                {{ $poste->departement ?? 'Général' }}
            </span>

            @if ($hasChildren)
                <button
                    type="button"
                    wire:click="toggleNode({{ $poste->id }})"
                    class="amb-node-btn-icon"
                    style="width: 1.5rem; height: 1.5rem;"
                    title="{{ $isCollapsed ? 'Développer les sous-postes' : 'Réduire les sous-postes' }}"
                >
                    <x-filament::icon :icon="$isCollapsed ? 'heroicon-o-chevron-down' : 'heroicon-o-chevron-up'" class="w-3.5 h-3.5" />
                </button>
            @endif
        </div>

        <!-- Corps : Nom de la fonction & description -->
        <div class="amb-node-body">
            <div class="amb-node-title">
                {{ $poste->nom }}
            </div>

            @if ($poste->description)
                <div class="amb-node-desc" title="{{ $poste->description }}">
                    {{ $poste->description }}
                </div>
            @endif

            <!-- Collaborateur(s) occupant cette fonction -->
            <div class="amb-node-occupants">
                @if ($poste->employes->isNotEmpty())
                    @foreach ($poste->employes as $emp)
                        <div class="amb-occupant-item">
                            <div class="amb-occupant-info">
                                <div class="amb-avatar">
                                    {{ substr($emp->nom, 0, 1) }}{{ substr($emp->prenom, 0, 1) }}
                                </div>
                                <div style="min-width: 0;">
                                    <div class="amb-occupant-name" title="{{ $emp->nom_complet }}">
                                        {{ $emp->nom_complet }}
                                    </div>
                                    <div class="amb-occupant-sub" title="{{ $emp->matricule }} {{ $emp->email }}">
                                        {{ $emp->matricule }}
                                    </div>
                                </div>
                            </div>
                            <span class="amb-badge-en-poste" title="Collaborateur en fonction">
                                <span class="amb-dot-pulse"></span>
                                Actif
                            </span>
                        </div>
                    @endforeach
                @else
                    <!-- Poste vacant (information uniquement) -->
                    <div class="amb-node-vacant">
                        <span class="amb-node-vacant-text">
                            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-3.5 h-3.5 text-amber-500" />
                            Poste non pourvu (Vacant)
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Nœuds enfants (Lignes hiérarchiques connectées et centrées) -->
    @if ($hasChildren && !$isCollapsed)
        <div class="amb-tree-children {{ count($children) === 1 ? 'single-child' : '' }}">
            @foreach ($children as $childNode)
                @include('socle::filament.pages.partials.org-tree-node', ['node' => $childNode, 'isRoot' => false])
            @endforeach
        </div>
    @endif
</div>
