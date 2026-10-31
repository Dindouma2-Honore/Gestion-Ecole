<?php

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Filament\Support\ModuleAccess;
use App\Filament\Support\RecordModal;
use App\Modules\Pedagogie\Models\DevoirPedagogique;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\Pedagogie\Services\ProgrammeAccessService;
use App\Modules\Pedagogie\Services\SessionProgressService;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProgressionResource extends Resource
{
    protected static ?string $model = Progression::class;

    protected static ?string $modelLabel = 'progression de séance';

    protected static ?string $pluralModelLabel = 'Progression des séances';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return app(ProgrammeAccessService::class)->autorise() && ModuleAccess::canAccessComponent(auth()->user(), static::class);
    }

    public static function canView(Model $record): bool
    {
        return static::getEloquentQuery()->whereKey($record->id)->exists();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny() && app(ProgrammeAccessService::class)->enseignantId() !== null;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate() && (int) $record->saisi_par === (int) auth()->id() && $record->created_at->gte(now()->subHours(24)) && $record->etat_avancement !== null;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $access = app(ProgrammeAccessService::class);
        $q = parent::getEloquentQuery()->with('seance.emploiDuTemps.matiere', 'chapitre');
        if (! static::canViewAny()) {
            return $q->whereRaw('1=0');
        }
        if ($access->supervise()) {
            return $q->whereHas('chapitre', fn ($q) => $q->whereIn('programme_id', $access->visibleQuery()->select('id')));
        }

        return $q->whereHas('seance', fn ($q) => $q->where('enseignant_id', $access->enseignantId() ?? -1));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('seance_id')->default(fn () => request()->integer('seance') ?: null)->label('Séance réalisée')->options(fn () => app(SessionProgressService::class)->seances()->get()->mapWithKeys(fn ($s) => [$s->id => $s->emploiDuTemps->matiere?->nom.' — '.$s->date_seance->format('d/m/Y')]))->required()->searchable()->live()->disabled(fn (?Progression $record) => $record !== null)->dehydrated(),
            Select::make('chapitre_id')->label('Leçon / objectif du programme validé')->options(function (Get $get, ?Progression $record) {
                $p = $record?->chapitre?->programme;
                if (! $p && ($s = app(SessionProgressService::class)->seances()->find($get('seance_id')))) {
                    $p = app(SessionProgressService::class)->programme($s);
                }
                if (! $p) {
                    return [];
                }
                $all = $p->chapitres()->get();
                $parents = $all->pluck('parent_id')->filter()->all();

                return $all->reject(fn ($e) => in_array($e->id, $parents))->mapWithKeys(fn ($e) => [$e->id => ($e->parent_id ? ($all->find($e->parent_id)?->titre.' — ') : '').$e->titre])->all();
            })->required()->searchable(),
            Select::make('etat_avancement')->label('État d’avancement')->options(['commencee' => 'Commencée', 'en_cours' => 'Poursuivie', 'terminee' => 'Terminée'])->required(),
            Textarea::make('contenu_couvert')->label('Contenu réellement traité')->required(), Textarea::make('observation')->label('Observation'),
            Toggle::make('devoir_donne')->label('Devoir donné ?')->live()->default(false),
            Select::make('type_devoir')->label('Type de devoir')->options(['exercice' => 'Exercice', 'maison' => 'Devoir maison', 'recherche' => 'Recherche', 'autre' => 'Autre'])->visible(fn (Get $get) => (bool) $get('devoir_donne'))->required(fn (Get $get) => (bool) $get('devoir_donne')),
            TextInput::make('consigne')->label('Titre / consigne')->maxLength(255)->visible(fn (Get $get) => (bool) $get('devoir_donne'))->required(fn (Get $get) => (bool) $get('devoir_donne')),
            Textarea::make('description_devoir')->label('Description du devoir')->visible(fn (Get $get) => (bool) $get('devoir_donne')),
            DatePicker::make('date_remise')->label('Date de remise')->visible(fn (Get $get) => (bool) $get('devoir_donne'))->required(fn (Get $get) => (bool) $get('devoir_donne')),
        ]);
    }

    public static function recordData(array $data, Progression $record): array
    {
        $d = DevoirPedagogique::where('progression_id', $record->id)->first();

        return $data + ['devoir_donne' => $d !== null, 'type_devoir' => $d?->type, 'consigne' => $d?->consigne, 'description_devoir' => $d?->description, 'date_remise' => $d?->date_remise];
    }

    public static function createAction(): CreateAction
    {
        return AmbassadorsDesign::goldenFormModal(CreateAction::make()->label('Renseigner une séance'))->using(fn (array $data) => app(SessionProgressService::class)->enregistrer($data));
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure(AmbassadorsDesign::configureTable($table, null)->columns([
            TextColumn::make('seance.date_seance')->label('Séance')->date(), TextColumn::make('seance.emploiDuTemps.matiere.nom')->label('Matière'),
            TextColumn::make('chapitre.titre')->label('Élément traité')->searchable(), TextColumn::make('etat_avancement')->label('État')->badge(), TextColumn::make('contenu_couvert')->label('Contenu')->limit(70), TextColumn::make('devoirs_donnes')->label('Devoir')->limit(45),
        ])->recordActions([ViewAction::make()->mutateRecordDataUsing(fn (array $data, Progression $record) => static::recordData($data, $record)), EditAction::make()->mutateRecordDataUsing(fn (array $data, Progression $record) => static::recordData($data, $record))->using(fn (Progression $record, array $data) => app(SessionProgressService::class)->enregistrer($data, $record->id))])->defaultSort('id', 'desc'), static::class);
    }

    private static function seancesDisponiblesPourSaisie(): Builder
    {
        $query = Seance::query();
        if (static::estProfilAdministratif()) {
            return $query;
        }

        $userId = Auth::id();
        $enseignantId = $userId ? app(EnseignantServiceInterface::class)->getEnseignantIdPourUtilisateur((int) $userId) : null;
        if ($enseignantId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('enseignant_id', $enseignantId)
            ->whereHas('emploiDuTemps', fn (Builder $emplois) => $emplois->whereExists(fn ($offres) => $offres
                ->selectRaw('1')->from('offres_pedagogiques as o')
                ->join('affectations_pedagogiques as a', 'a.offre_pedagogique_id', '=', 'o.id')
                ->whereColumn('o.classe_id', 'emplois_du_temps.classe_id')
                ->whereColumn('o.matiere_id', 'emplois_du_temps.matiere_id')
                ->whereColumn('o.annee_scolaire_id', 'emplois_du_temps.annee_scolaire_id')
                ->whereColumn('a.classe_id', 'o.classe_id')
                ->whereColumn('a.matiere_id', 'o.matiere_id')
                ->where('o.actif', true)
                ->where('a.enseignant_id', $enseignantId)
                ->whereColumn('a.annee_scolaire_id', 'emplois_du_temps.annee_scolaire_id')
                ->where('a.actif', true)));
    }

    private static function estProfilAdministratif(): bool
    {
        $user = Auth::user();
        return $user !== null && ($user->hasAnyRole(['Fondateur', 'Admin', 'Administrateur', 'Super Admin', 'Directeur']) || $user->can('pedagogie.progression.superviser'));
    }

    public static function getPages(): array
    {
        return ['index' => ProgressionResource\Pages\ListProgressions::route('/')];
    }
}
