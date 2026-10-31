<?php

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Contracts\AffectationPedagogiqueServiceInterface;
use App\Modules\Pedagogie\Enums\TypeAffectationPedagogique;
use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource\Pages;
use App\Modules\Pedagogie\Filament\Support\OrganisationResource;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Services\OffrePedagogiqueService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AffectationPedagogiqueResource extends OrganisationResource
{
    protected static bool $isDiscovered = true;

    protected static ?string $model = AffectationPedagogique::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $modelLabel = 'affectation pédagogique';

    protected static ?string $pluralModelLabel = 'Affectations pédagogiques';

    public static function form(Schema $schema): Schema
    {
        $existing = fn (?Model $record) => $record?->exists ?? false;

        return $schema->columns(2)->components([
            static::yearField()->afterStateUpdated(fn (Set $set) => $set('classe_id', null)),
            Select::make('classe_id')->label('Classe')->options(fn (Get $get) => collect(static::context()->classes((int) $get('annee_scolaire_id')))->where('statut', 'active')->pluck('nom', 'id')->all())
                ->required()->searchable()->live()->disabled($existing)->dehydrated()
                ->afterStateUpdated(function (Get $get, Set $set): void {
                    $set('type', static::context()->modeClasse((int) $get('annee_scolaire_id'), (int) $get('classe_id')) === 'matiere' ? 'matiere' : 'principal');
                    $set('matiere_id', null);
                    $set('matiere_ids', []);
                }),
            Select::make('type')->label('Responsabilité')->options(function (Get $get): array {
                $options = TypeAffectationPedagogique::options();

                return static::context()->modeClasse((int) $get('annee_scolaire_id'), (int) $get('classe_id')) === 'matiere' ? array_intersect_key($options, ['matiere' => 1]) : array_intersect_key($options, ['principal' => 1, 'assistant' => 1]);
            })->required()->live()->disabled($existing)->dehydrated()->afterStateUpdated(fn (Set $set) => $set('matiere_ids', [])),
            Select::make('enseignant_id')->label('Enseignant')->options(fn () => collect(static::context()->enseignants())->where('actif', true)->pluck('nom', 'id')->all())->required()->searchable()->disabled($existing)->dehydrated(),
            Select::make('matiere_id')->label('Matière / domaine')->options(fn (Get $get) => static::matieresDisponibles((int) $get('annee_scolaire_id'), (int) $get('classe_id')))
                ->visible(fn (Get $get) => $get('type') === 'matiere')->required(fn (Get $get) => $get('type') === 'matiere')->disabled($existing)->dehydrated()->searchable(),
            Select::make('matiere_ids')->label('Spécialités de l’assistant')->multiple()->options(fn (Get $get) => static::matieresDisponibles((int) $get('annee_scolaire_id'), (int) $get('classe_id')))
                ->visible(fn (Get $get) => $get('type') === 'assistant')->helperText('Laissez vide pour un assistant généraliste.')->searchable(),
            DatePicker::make('date_debut')->label('Début de responsabilité')->helperText('Par défaut : début de l’année scolaire.'),
            DatePicker::make('date_fin')->label('Fin de responsabilité')->helperText('Par défaut : fin de l’année scolaire.'),
            Toggle::make('actif')->label('Affectation active')->default(true),
        ]);
    }

    public static function matieresDisponibles(int $anneeId, int $classeId): array
    {
        return Matiere::where('actif', true)->orderBy('nom')->get()->filter(fn ($m) => app(OffrePedagogiqueService::class)->effective($anneeId, $classeId, $m->id))->pluck('nom', 'id')->all();
    }

    public static function table(Table $table): Table
    {
        return static::organisationTable($table)->columns([
            TextColumn::make('classe_id')->label('Classe')->formatStateUsing(fn ($state, $record) => static::context()->classes((int) $record->annee_scolaire_id)[$state]['nom'] ?? '—'),
            TextColumn::make('enseignant_id')->label('Enseignant')->formatStateUsing(fn ($state) => static::context()->enseignants()[$state]['nom'] ?? '—'),
            TextColumn::make('type')->label('Responsabilité')->badge()->formatStateUsing(fn ($state) => TypeAffectationPedagogique::tryFrom($state)?->label() ?? $state),
            TextColumn::make('matiere.nom')->label('Matière')->placeholder('Toutes les matières autorisées'),
            TextColumn::make('matieresSpecialisees.nom')->label('Spécialités')->listWithLineBreaks()->placeholder('—'),
            TextColumn::make('date_debut')->label('Du')->date('d/m/Y'), TextColumn::make('date_fin')->label('Au')->date('d/m/Y'),
            IconColumn::make('actif')->label('Active')->boolean(),
        ])->filters([static::yearFilter(), SelectFilter::make('type')->label('Responsabilité')->options(TypeAffectationPedagogique::options())]);
    }

    public static function recordData(array $data, Model $record): array
    {
        $data['matiere_ids'] = $record->matieresSpecialisees->modelKeys();

        return $data;
    }

    public static function getEloquentQuery(): Builder
    {
        $ids = [];
        foreach (array_keys(static::context()->annees()) as $year) {
            $ids = array_merge($ids, array_keys(static::context()->classes($year)));
        }

        return parent::getEloquentQuery()->with(['matiere', 'matieresSpecialisees'])->whereIn('classe_id', $ids);
    }

    public static function enregistrer(array $data, ?Model $record = null): Model
    {
        return app(AffectationPedagogiqueServiceInterface::class)->enregistrer($data, $record?->getKey());
    }

    // Compatibility for the existing programme form; decision comes from configured cycle settings.
    public static function classeEstSecondaire(int $classeId): bool
    {
        return static::context()->modeClasse((int) static::context()->anneeCourante(), $classeId) === 'matiere';
    }

    public static function classesDisponibles(?int $anneeId = null): array
    {
        $context = static::context();
        $anneeId ??= $context->anneeCourante();

        return $anneeId ? $context->classes($anneeId) : [];
    }

    public static function enseignantsDisponibles(): array
    {
        return collect(static::context()->enseignants())->where('actif', true)->pluck('nom', 'id')->all();
    }

    public static function offresDisponibles(int $classeId): array
    {
        $context = static::context();
        $anneeId = $context->anneeCourante();
        $classe = $anneeId ? ($context->classes($anneeId)[$classeId] ?? null) : null;
        if (! $classe) {
            return [];
        }

        return OffrePedagogique::query()->where('annee_scolaire_id', $anneeId)->where('actif', true)
            ->where(fn ($q) => $q->where('classe_id', $classeId)->orWhere('niveau_id', $classe['niveau_id']))
            ->pluck('id', 'id')->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffectationsPedagogiques::route('/'),
            'create' => Pages\CreateAffectationPedagogique::route('/create'),
            'edit' => Pages\EditAffectationPedagogique::route('/{record}/edit'),
        ];
    }
}
