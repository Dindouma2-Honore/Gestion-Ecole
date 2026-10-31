<?php

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource\Pages;
use App\Modules\Pedagogie\Filament\Support\OrganisationResource;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Services\OffrePedagogiqueService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

class OffrePedagogiqueResource extends OrganisationResource
{
    protected static bool $isDiscovered = true;

    protected static ?string $model = OffrePedagogique::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $modelLabel = 'offre pédagogique';

    protected static ?string $pluralModelLabel = 'offres pédagogiques';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            static::yearField()->afterStateUpdated(fn (Set $set) => $set('classe_id', null)),
            Select::make('niveau_id')->label('Niveau')->options(fn () => collect(static::context()->niveaux())->mapWithKeys(fn ($n) => [$n['id'] => (static::context()->cycles()[$n['cycle_id']]['nom'] ?? '').' — '.$n['nom']])->all())
                ->required()->searchable()->live()->afterStateUpdated(fn (Set $set) => $set('classe_id', null))->disabled(fn (?Model $record) => $record?->exists ?? false)->dehydrated(),
            Select::make('classe_id')->label('Classe (facultatif)')->placeholder('Toutes les classes du niveau')
                ->options(fn (Get $get) => collect(static::context()->classes((int) $get('annee_scolaire_id')))->where('niveau_id', $get('niveau_id'))->where('statut', 'active')->pluck('nom', 'id')->all())
                ->searchable()->disabled(fn (?Model $record) => $record?->exists ?? false)->dehydrated()->helperText('Sans classe : offre commune du niveau. Avec classe : adaptation prioritaire pour cette classe.'),
            Select::make('matiere_id')->label('Matière / domaine')->relationship('matiere', 'nom')->required()->searchable()->preload()->disabled(fn (?Model $record) => $record?->exists ?? false)->dehydrated(),
            TextInput::make('coefficient_matiere')->label('Coefficient annuel')->numeric()->minValue(.01)->default(1)->required(),
            TextInput::make('volume_horaire')->label('Volume annuel (h)')->numeric()->minValue(0)->maxValue(99999.99),
            TextInput::make('heures_hebdomadaires')->label('Heures par semaine')->numeric()->minValue(0)->maxValue(168),
            Toggle::make('actif')->label('Offre active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::organisationTable($table)->columns([
            TextColumn::make('matiere.nom')->label('Matière / domaine')->searchable(),
            TextColumn::make('niveau_id')->label('Niveau')->formatStateUsing(fn ($state) => static::context()->niveaux()[$state]['nom'] ?? '—'),
            TextColumn::make('classe_id')->label('Classe')->placeholder('Toutes les classes du niveau')->formatStateUsing(fn ($state, $record) => static::context()->classes((int) $record->annee_scolaire_id)[$state]['nom'] ?? '—'),
            TextColumn::make('coefficient_matiere')->label('Coefficient'),
            TextColumn::make('heures_hebdomadaires')->label('H/semaine'),
            IconColumn::make('actif')->label('Active')->boolean(),
        ])->filters([static::yearFilter(), SelectFilter::make('niveau_id')->label('Niveau')->options(fn () => collect(static::context()->niveaux())->pluck('nom', 'id')->all())]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('matiere')->whereIn('niveau_id', array_keys(static::context()->niveaux()));
    }

    public static function classesDisponibles(?int $anneeId = null): array
    {
        $context = static::context();
        $anneeId ??= $context->anneeCourante();

        return $anneeId ? $context->classes($anneeId) : [];
    }

    public static function enregistrer(array $data, ?Model $record = null): Model
    {
        return app(OffrePedagogiqueService::class)->enregistrer($data, $record?->getKey());
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOffresPedagogiques::route('/'), 'edit' => Pages\EditOffrePedagogique::route('/{record}/edit')];
    }
}
