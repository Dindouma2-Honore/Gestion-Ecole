<nav class="habilitation-tabs" aria-label="Type d’habilitation">
    <style>
        .habilitation-tabs{display:flex;flex-wrap:wrap;gap:.55rem;margin-bottom:1.25rem;padding:.45rem;border:1px solid #dce3ef;border-radius:1rem;background:#fff;box-shadow:0 8px 24px rgba(7,22,63,.06)}.habilitation-tabs a{display:inline-flex;align-items:center;gap:.45rem;border-radius:.7rem;padding:.7rem 1rem;color:#52617e;font-size:.82rem;font-weight:800;text-decoration:none;transition:background .2s,color .2s,box-shadow .2s}.habilitation-tabs a:hover{background:#edf4ff;color:#1948bd}.habilitation-tabs a.is-active{background:linear-gradient(135deg,#1948bd,#102f78);color:#fff;box-shadow:0 7px 16px rgba(25,72,189,.24)}.habilitation-tabs svg{width:1.1rem;height:1.1rem}.dark .habilitation-tabs{border-color:#253459;background:#09142f}.dark .habilitation-tabs a{color:#b8c5e0}.dark .habilitation-tabs a.is-active{color:#fff}@media(max-width:520px){.habilitation-tabs{display:grid;grid-template-columns:1fr}.habilitation-tabs a{justify-content:center}}
    </style>

    <a href="{{ \App\Modules\Socle\Filament\Pages\ManageHabilitations::getUrl() }}"
       @class(['is-active' => $active === 'roles'])
       @if($active === 'roles') aria-current="page" @endif>
        <x-filament::icon icon="heroicon-o-identification" />
        Rôles du personnel
    </a>

    <a href="{{ \App\Modules\Socle\Filament\Pages\ManageGroupePermissions::getUrl() }}"
       @class(['is-active' => $active === 'groupes'])
       @if($active === 'groupes') aria-current="page" @endif>
        <x-filament::icon icon="heroicon-o-user-group" />
        Groupes
    </a>

    <a href="{{ \App\Modules\Socle\Filament\Pages\ManageUserPermissions::getUrl() }}"
       @class(['is-active' => $active === 'utilisateurs'])
       @if($active === 'utilisateurs') aria-current="page" @endif>
        <x-filament::icon icon="heroicon-o-user" />
        Utilisateurs
    </a>
</nav>
