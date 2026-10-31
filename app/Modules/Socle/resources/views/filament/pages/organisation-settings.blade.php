<x-filament-panels::page>
    <style>
        /* Styles Socle Organigramme Hiérarchique (Affichage Complet, Centré & Non Tronqué) */
        .amb-org-wrapper {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            color: #1e293b;
            font-family: inherit;
        }

        /* KPI Banner */
        .amb-org-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .amb-org-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.15rem 1.35rem;
            box-shadow: 0 4px 12px rgba(7, 22, 63, 0.04);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s ease;
        }

        .amb-org-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(7, 22, 63, 0.08);
        }

        .amb-org-kpi-icon {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .amb-org-kpi-val {
            font-size: 1.6rem;
            font-weight: 800;
            color: #07163f;
            line-height: 1.2;
        }

        .amb-org-kpi-lbl {
            font-size: 0.825rem;
            font-weight: 500;
            color: #64748b;
        }

        /* Toolbar */
        .amb-org-toolbar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 0.85rem 1.25rem;
            box-shadow: 0 2px 8px rgba(7, 22, 63, 0.03);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .amb-org-filters {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .amb-org-search-box {
            position: relative;
            min-width: 220px;
            flex: 1;
            max-width: 320px;
        }

        .amb-org-search-box input {
            width: 100%;
            padding: 0.55rem 0.85rem 0.55rem 2.3rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            font-size: 0.85rem;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .amb-org-search-box input:focus {
            border-color: #1948bd;
            box-shadow: 0 0 0 3px rgba(25, 72, 189, 0.15);
        }

        .amb-org-search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
            width: 1rem;
            height: 1rem;
        }

        .amb-org-select {
            padding: 0.55rem 0.85rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            font-size: 0.85rem;
            background-color: #ffffff;
            color: #334155;
            outline: none;
            cursor: pointer;
        }

        .amb-org-select:focus {
            border-color: #1948bd;
        }

        .amb-org-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .amb-btn-secondary {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 0.5rem 0.85rem;
            border-radius: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .amb-btn-secondary:hover {
            background-color: #e2e8f0;
            color: #0f172a;
        }

        .amb-view-toggle {
            display: inline-flex;
            background: #f1f5f9;
            padding: 0.25rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
        }

        .amb-view-btn {
            padding: 0.35rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 0.55rem;
            border: none;
            cursor: pointer;
            background: transparent;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.15s ease;
        }

        .amb-view-btn.active {
            background: #ffffff;
            color: #07163f;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        /* Zoom controls */
        .amb-zoom-group {
            display: inline-flex;
            align-items: center;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.15rem;
            gap: 0.2rem;
        }

        .amb-zoom-btn {
            background: transparent;
            border: none;
            color: #475569;
            padding: 0.35rem 0.55rem;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            border-radius: 0.5rem;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .amb-zoom-btn:hover {
            background: #ffffff;
            color: #07163f;
        }

        .amb-zoom-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #07163f;
            padding: 0 0.35rem;
            min-width: 2.8rem;
            text-align: center;
        }

        /* 
           ZONE DE DÉFILEMENT DE L'ORGANIGRAMME (ZÉRO TRONCATURE À GAUCHE)
           Utilise display: block + text-align: center avec inline-flex interne
           afin que si le contenu déborde, scrollLeft = 0 affiche l'extrême gauche !
        */
        .amb-tree-scroll-container {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 1.25rem;
            padding: 2.5rem 1.5rem;
            overflow-x: auto;
            overflow-y: visible;
            min-height: 520px;
            box-shadow: inset 0 2px 8px rgba(7, 22, 63, 0.02);
            text-align: center;
            width: 100%;
            box-sizing: border-box;
        }

        .amb-tree-wrapper {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            margin: 0 auto;
            text-align: left;
            min-width: min-content;
            width: max-content;
            transition: transform 0.15s ease;
        }

        .amb-tree-roots {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 2.5rem;
            width: 100%;
        }

        .amb-tree-node-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        /* Connecteurs hiérarchiques symétriques */
        .amb-tree-node-wrapper.has-children > .amb-tree-node-card::after,
        .amb-tree-child-wrapper.has-children > .amb-tree-node-card::after {
            content: '';
            position: absolute;
            bottom: -1rem;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 1rem;
            background-color: #94a3b8;
        }

        .amb-tree-children {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 1.5rem;
            position: relative;
            padding-top: 2rem;
            margin-top: 0;
            width: 100%;
        }

        /* Tige verticale depuis le parent descendant vers la barre horizontale */
        .amb-tree-children::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 1rem;
            background-color: #94a3b8;
        }

        .amb-tree-child-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        /* Tige verticale vers la carte enfant */
        .amb-tree-child-wrapper::before {
            content: '';
            position: absolute;
            top: -1rem;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 1rem;
            background-color: #94a3b8;
        }

        /* Barre horizontale reliant tous les enfants */
        .amb-tree-child-wrapper::after {
            content: '';
            position: absolute;
            top: -1rem;
            width: 100%;
            height: 2px;
            background-color: #94a3b8;
        }

        .amb-tree-child-wrapper:first-child::after {
            left: 50%;
            width: 50%;
        }

        .amb-tree-child-wrapper:last-child::after {
            left: 0;
            width: 50%;
        }

        .amb-tree-child-wrapper:only-child::after {
            display: none;
        }

        .amb-tree-child-wrapper:only-child::before {
            top: -2rem;
            height: 2rem;
        }

        /* Node Card Design (Compact, lisible et élégant) */
        .amb-tree-node-card {
            background: #ffffff;
            border: 2px solid #cbd5e1;
            border-radius: 1rem;
            width: 260px;
            box-shadow: 0 4px 14px rgba(7, 22, 63, 0.05);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 2;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .amb-tree-node-card:hover {
            border-color: #1948bd;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(7, 22, 63, 0.1);
        }

        /* Card Header */
        .amb-node-header {
            padding: 0.65rem 0.85rem 0.45rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f1f5f9;
            background: #fafafa;
        }

        .amb-node-level {
            font-size: 0.68rem;
            font-weight: 800;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .amb-lvl-1 { background: #07163f; color: #ffffff; }
        .amb-lvl-2 { background: #1948bd; color: #ffffff; }
        .amb-lvl-3 { background: #e0e7ff; color: #3730a3; }
        .amb-lvl-4 { background: #fef3c7; color: #92400e; }
        .amb-lvl-5 { background: #f1f5f9; color: #475569; }

        .amb-node-dept {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 140px;
        }

        /* Card Body */
        .amb-node-body {
            padding: 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
        }

        .amb-node-title {
            font-size: 0.98rem;
            font-weight: 800;
            color: #07163f;
            line-height: 1.25;
            letter-spacing: -0.01em;
            text-align: center;
        }

        .amb-node-desc {
            font-size: 0.74rem;
            color: #64748b;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-align: center;
        }

        /* Occupants */
        .amb-node-occupants {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            padding-top: 0.45rem;
            border-top: 1px dashed #e2e8f0;
        }

        .amb-occupant-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.4rem 0.55rem;
            background: #f8fafc;
            border-radius: 0.65rem;
            border: 1px solid #f1f5f9;
        }

        .amb-occupant-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
            flex: 1;
        }

        .amb-avatar {
            width: 1.9rem;
            height: 1.9rem;
            border-radius: 9999px;
            background: linear-gradient(135deg, #1948bd, #07163f);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.72rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            text-transform: uppercase;
        }

        .amb-occupant-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }

        .amb-occupant-sub {
            font-size: 0.68rem;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .amb-badge-en-poste {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.65rem;
            font-weight: 700;
            color: #16a34a;
            background: #dcfce7;
            padding: 0.15rem 0.45rem;
            border-radius: 9999px;
            flex-shrink: 0;
        }

        .amb-dot-pulse {
            width: 5px;
            height: 5px;
            background-color: #16a34a;
            border-radius: 9999px;
        }

        /* Vacant Node Badge */
        .amb-node-vacant {
            background: #fffbeb;
            border: 1px dashed #f59e0b;
            border-radius: 0.65rem;
            padding: 0.55rem 0.7rem;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            align-items: center;
            text-align: center;
        }

        .amb-node-vacant-text {
            font-size: 0.72rem;
            font-weight: 700;
            color: #b45309;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .amb-node-btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.65rem;
            height: 1.65rem;
            border-radius: 0.45rem;
            background: transparent;
            border: none;
            color: #64748b;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .amb-node-btn-icon:hover {
            background: #e2e8f0;
            color: #07163f;
        }

        /* Tabular View */
        .amb-table-container {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1.15rem;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(7, 22, 63, 0.04);
        }

        .amb-org-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.85rem;
        }

        .amb-org-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.85rem 1.15rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .amb-org-table td {
            padding: 0.9rem 1.15rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .amb-org-table tr:hover td {
            background-color: #fafcff;
        }

        .amb-badge-pourvu {
            background: #dcfce7;
            color: #15803d;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .amb-badge-vacant {
            background: #fef3c7;
            color: #b45309;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Notice d'information */
        .amb-notice-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.85rem;
            padding: 0.75rem 1rem;
            font-size: 0.8rem;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
    </style>

    <div class="amb-org-wrapper">

        <!-- 1. En-tête KPI -->
        @php
            $stats = $this->stats;
        @endphp
        <div class="amb-org-kpis">
            <div class="amb-org-kpi-card">
                <div class="amb-org-kpi-icon" style="background: rgba(7, 22, 63, 0.08); color: #07163f;">
                    <x-filament::icon icon="heroicon-o-briefcase" class="w-5 h-5" />
                </div>
                <div>
                    <div class="amb-org-kpi-val">{{ $stats['totalFonctions'] }}</div>
                    <div class="amb-org-kpi-lbl">Fonctions administratives</div>
                </div>
            </div>

            <div class="amb-org-kpi-card">
                <div class="amb-org-kpi-icon" style="background: rgba(22, 163, 74, 0.12); color: #16a34a;">
                    <x-filament::icon icon="heroicon-o-check-badge" class="w-5 h-5" />
                </div>
                <div>
                    <div class="amb-org-kpi-val">{{ $stats['fonctionsPourvues'] }} <span style="font-size: 0.9rem; font-weight: 600; color: #16a34a;">({{ $stats['tauxCouverture'] }}%)</span></div>
                    <div class="amb-org-kpi-lbl">Fonctions pourvues</div>
                </div>
            </div>

            <div class="amb-org-kpi-card">
                <div class="amb-org-kpi-icon" style="background: rgba(245, 158, 11, 0.12); color: #d97706;">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
                </div>
                <div>
                    <div class="amb-org-kpi-val">{{ $stats['fonctionsVacantes'] }}</div>
                    <div class="amb-org-kpi-lbl">Postes vacants (non pourvus)</div>
                </div>
            </div>

            <div class="amb-org-kpi-card">
                <div class="amb-org-kpi-icon" style="background: rgba(25, 72, 189, 0.1); color: #1948bd;">
                    <x-filament::icon icon="heroicon-o-users" class="w-5 h-5" />
                </div>
                <div>
                    <div class="amb-org-kpi-val">{{ $stats['totalCollaborateurs'] }}</div>
                    <div class="amb-org-kpi-lbl">Collaborateurs en poste</div>
                </div>
            </div>
        </div>

        <!-- 2. Barre d'outils de consultation -->
        <div class="amb-org-toolbar">
            <div class="amb-org-filters">
                <!-- Recherche en temps réel -->
                <div class="amb-org-search-box">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="amb-org-search-icon" />
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="search"
                        placeholder="Rechercher une fonction, collaborateur ou matricule..."
                    />
                </div>

                <!-- Filtre Pôle / Département -->
                <select wire:model.live="selectedDepartement" class="amb-org-select">
                    <option value="all">Tous les pôles / départements</option>
                    @foreach ($this->departementsList as $dept)
                        <option value="{{ $dept }}">{{ $dept }}</option>
                    @endforeach
                </select>

                <!-- Filtre Statut -->
                <select wire:model.live="selectedStatut" class="amb-org-select">
                    <option value="all">Tous les statuts</option>
                    <option value="pourvu">Fonctions pourvues</option>
                    <option value="vacant">Postes vacants</option>
                </select>

                @if ($search !== '' || $selectedDepartement !== 'all' || $selectedStatut !== 'all' || $zoom !== 95)
                    <button wire:click="resetFilters" class="amb-btn-secondary">
                        <x-filament::icon icon="heroicon-o-x-mark" class="w-4 h-4" />
                        Réinitialiser
                    </button>
                @endif
            </div>

            <div class="amb-org-actions">
                <!-- Contrôles de Zoom -->
                @if ($viewMode === 'organigramme')
                    <div class="amb-zoom-group" title="Ajuster l'échelle de l'organigramme">
                        <button type="button" wire:click="zoomOut" class="amb-zoom-btn" title="Réduire la taille (-10%)">
                            <x-filament::icon icon="heroicon-o-minus" class="w-3.5 h-3.5" />
                        </button>
                        <span class="amb-zoom-label">{{ $zoom }}%</span>
                        <button type="button" wire:click="zoomIn" class="amb-zoom-btn" title="Agrandir la taille (+10%)">
                            <x-filament::icon icon="heroicon-o-plus" class="w-3.5 h-3.5" />
                        </button>
                    </div>
                @endif

                <!-- Développer / Réduire tout -->
                <button
                    type="button"
                    wire:click="expandAll"
                    class="amb-btn-secondary"
                    title="Développer tous les niveaux de l'organigramme"
                >
                    <x-filament::icon icon="heroicon-o-arrows-pointing-out" class="w-4 h-4" />
                    Déplier tout
                </button>

                <button
                    type="button"
                    wire:click="collapseAll"
                    class="amb-btn-secondary"
                    title="Réduire tous les niveaux"
                >
                    <x-filament::icon icon="heroicon-o-arrows-pointing-in" class="w-4 h-4" />
                    Replier tout
                </button>

                <!-- Bascule Vue Arbre / Vue Tableau -->
                <div class="amb-view-toggle">
                    <button
                        type="button"
                        wire:click="$set('viewMode', 'organigramme')"
                        class="amb-view-btn {{ $viewMode === 'organigramme' ? 'active' : '' }}"
                    >
                        <x-filament::icon icon="heroicon-o-rectangle-group" class="w-4 h-4" />
                        Organigramme
                    </button>
                    <button
                        type="button"
                        wire:click="$set('viewMode', 'tableau')"
                        class="amb-view-btn {{ $viewMode === 'tableau' ? 'active' : '' }}"
                    >
                        <x-filament::icon icon="heroicon-o-table-cells" class="w-4 h-4" />
                        Tableau
                    </button>
                </div>
            </div>
        </div>

        <!-- Notice explicative de consultation pure -->
        <div class="amb-notice-box">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <x-filament::icon icon="heroicon-o-information-circle" class="w-4 h-4 text-blue-500 shrink-0" />
                <span>Cet écran présente l'organisation hiérarchique officielle de l'établissement d'après les données administratives et contractuelles du module <strong>Ressources Humaines</strong>.</span>
            </div>
            @if ($viewMode === 'organigramme')
                <span style="font-size: 0.75rem; color: #94a3b8;">
                    Astuce : utilisez les boutons de zoom ou faites glisser horizontalement pour parcourir l'ensemble de la hiérarchie.
                </span>
            @endif
        </div>

        <!-- 3. CONTENU PRINCIPAL : ORGANIGRAMME VISUEL NON TRONQUÉ OU TABLEAU -->
        @if ($viewMode === 'organigramme')
            <div class="amb-tree-scroll-container">
                @php
                    $tree = $this->hierarchyTree;
                @endphp

                @if (empty($tree))
                    <div style="text-align: center; padding: 3rem 1rem; color: #64748b;">
                        <x-filament::icon icon="heroicon-o-magnifying-glass" class="w-12 h-12 mx-auto mb-3 text-slate-300" />
                        <div style="font-size: 1.1rem; font-weight: 700; color: #07163f;">Aucune fonction trouvée</div>
                        <p style="font-size: 0.85rem; margin-top: 0.25rem;">Modifiez vos critères de recherche pour visualiser l'organigramme.</p>
                        <button type="button" wire:click="resetFilters" class="amb-btn-secondary mt-3">Réinitialiser les filtres</button>
                    </div>
                @else
                    <div class="amb-tree-wrapper" style="transform: scale({{ $zoom / 100 }}); transform-origin: top center;">
                        <div class="amb-tree-roots">
                            @foreach ($tree as $node)
                                @include('socle::filament.pages.partials.org-tree-node', ['node' => $node, 'isRoot' => true])
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @else
            <!-- VUE TABLEAU SYNTHÉTIQUE (CONSULTATION PURE) -->
            <div class="amb-table-container">
                <table class="amb-org-table">
                    <thead>
                        <tr>
                            <th>Niveau</th>
                            <th>Pôle / Département</th>
                            <th>Fonction administrative</th>
                            <th>Supérieur direct (N+1)</th>
                            <th>Personnel en poste</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->allPostes as $poste)
                            <tr>
                                <td>
                                    @php
                                        $lvlClass = match ($poste->niveau_hierarchique) {
                                            1 => 'amb-lvl-1',
                                            2 => 'amb-lvl-2',
                                            3 => 'amb-lvl-3',
                                            4 => 'amb-lvl-4',
                                            default => 'amb-lvl-5',
                                        };
                                    @endphp
                                    <span class="amb-node-level {{ $lvlClass }}">N{{ $poste->niveau_hierarchique }}</span>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: #334155;">{{ $poste->departement ?? 'Non assigné' }}</span>
                                </td>
                                <td>
                                    <div style="font-weight: 800; color: #07163f; font-size: 0.95rem;">
                                        {{ $poste->nom }}
                                    </div>
                                    @if ($poste->description)
                                        <div style="font-size: 0.75rem; color: #64748b; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            {{ $poste->description }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($poste->parent)
                                        <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 600; color: #1948bd;">
                                            <x-filament::icon icon="heroicon-o-arrow-up-right" class="w-3.5 h-3.5" />
                                            {{ $poste->parent->nom }}
                                        </span>
                                    @else
                                        <span style="color: #94a3b8; font-style: italic;">Sommet (Gouvernance)</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($poste->employes->isNotEmpty())
                                        <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                            @foreach ($poste->employes as $emp)
                                                <div style="display: flex; align-items: center; gap: 0.5rem; background: #f8fafc; padding: 0.3rem 0.6rem; border-radius: 0.5rem; border: 1px solid #f1f5f9;">
                                                    <div class="amb-avatar" style="width: 1.6rem; height: 1.6rem; font-size: 0.65rem;">
                                                        {{ substr($emp->nom, 0, 1) }}{{ substr($emp->prenom, 0, 1) }}
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 700; font-size: 0.8rem; color: #0f172a;">{{ $emp->nom_complet }}</div>
                                                        <div style="font-size: 0.68rem; color: #64748b;">{{ $emp->matricule }}</div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="amb-badge-vacant">
                                            <x-filament::icon icon="heroicon-o-exclamation-circle" class="w-3.5 h-3.5" />
                                            Poste non pourvu
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if ($poste->employes->isNotEmpty())
                                        <span class="amb-badge-pourvu">
                                            <x-filament::icon icon="heroicon-o-check-circle" class="w-3.5 h-3.5" />
                                            {{ $poste->employes->count() }} occupant(s)
                                        </span>
                                    @else
                                        <span class="amb-badge-vacant">
                                            Vacant
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2.5rem; color: #64748b;">
                                    Aucune fonction ne correspond aux critères sélectionnés.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</x-filament-panels::page>
