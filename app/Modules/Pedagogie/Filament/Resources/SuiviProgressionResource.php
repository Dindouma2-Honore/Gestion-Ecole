<?php

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Services\OrganisationPedagogiqueContext;
use App\Modules\Pedagogie\Services\ProgressionMonitoringService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SuiviProgressionResource extends ProgrammeResource
{
    protected static ?string $slug = 'suivi-progressions';

    protected static ?string $pluralModelLabel = 'Suivi des progressions';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('statut', 'valide')->with('chapitres')->whereNotNull('classe_id');
    }

    public static function table(Table $table): Table
    {
        return AmbassadorsDesign::configureTable($table, null)->columns([
            TextColumn::make('titre')->label('Offre / programme')->searchable(),
            TextColumn::make('enseignant_id')->label('Enseignant')->formatStateUsing(fn ($state) => app(OrganisationPedagogiqueContext::class)->enseignants()[$state]['nom'] ?? '—'),
            TextColumn::make('version')->label('Version'),
            TextColumn::make('realise')->label('Programme réalisé')->getStateUsing(fn (Programme $record) => app(ProgressionMonitoringService::class)->mesure($record)['pourcentage'].' %'),
            TextColumn::make('temps')->label('Année écoulée')->getStateUsing(fn (Programme $record) => ($t = app(ProgressionMonitoringService::class)->mesure($record)['temps']) !== null ? $t.' %' : 'Calendrier à compléter'),
            TextColumn::make('ecart')->label('Écart (points)')->getStateUsing(fn (Programme $record) => app(ProgressionMonitoringService::class)->mesure($record)['ecart'] ?? '—'),
            TextColumn::make('derniere_maj')->label('Dernière progression')->getStateUsing(fn (Programme $record) => app(ProgressionMonitoringService::class)->mesure($record)['derniere_maj'] ?? 'Aucune'),
            TextColumn::make('alertes')->label('À traiter')->getStateUsing(fn (Programme $record) => app(ProgressionMonitoringService::class)->mesure($record)['alertes']),
        ])->filters([
            SelectFilter::make('annee_scolaire_id')->label('Année')->options(fn () => collect(app(OrganisationPedagogiqueContext::class)->annees())->pluck('libelle', 'id'))->default(fn () => app(OrganisationPedagogiqueContext::class)->anneeCourante()),
            SelectFilter::make('niveau_id')->label('Niveau')->options(fn () => collect(app(OrganisationPedagogiqueContext::class)->niveaux())->pluck('nom', 'id')),
            SelectFilter::make('cycle')->label('Cycle')->options(fn () => collect(app(OrganisationPedagogiqueContext::class)->cycles())->pluck('nom', 'id'))->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $cycle) => $q->whereIn('niveau_id', collect(app(OrganisationPedagogiqueContext::class)->niveaux())->where('cycle_id', $cycle)->pluck('id')))),
            SelectFilter::make('classe_id')->label('Classe')->options(fn () => static::getEloquentQuery()->get(['classe_id', 'titre'])->unique('classe_id')->mapWithKeys(fn ($p) => [$p->classe_id => $p->titre])->all()),
            SelectFilter::make('matiere_id')->label('Matière')->relationship('matiere', 'nom'),
            SelectFilter::make('enseignant_id')->label('Enseignant')->options(fn () => collect(app(OrganisationPedagogiqueContext::class)->enseignants())->pluck('nom', 'id')),
        ])->recordActions([
            AmbassadorsDesign::goldenDialogModal(Action::make('explication')->label('Comprendre la progression'))->modalHeading('Origine du calcul')->modalSubmitAction(false)->modalContent(fn (Programme $record) => view('pedagogie::filament.pages.progression-detail', ['programme' => $record, 'details' => app(ProgressionMonitoringService::class)->details($record), 'mesure' => app(ProgressionMonitoringService::class)->mesure($record)])),
        ])->recordAction('explication')->emptyStateHeading('Aucun programme validé dans ce contexte');
    }

    public static function getPages(): array
    {
        return ['index' => SuiviProgressionResource\Pages\ListSuiviProgressions::route('/')];
    }
}
