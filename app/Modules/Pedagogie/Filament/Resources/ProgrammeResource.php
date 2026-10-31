<?php

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Filament\Support\RecordModal;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Services\ProgramImportService;
use App\Modules\Pedagogie\Services\ProgrammeAccessService;
use App\Modules\Pedagogie\Services\ProgramStructureService;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ProgrammeResource extends Resource
{
    protected static ?string $model = Programme::class;

    protected static ?string $modelLabel = 'programme';

    protected static ?string $pluralModelLabel = 'Mes programmes';

    protected static bool $shouldRegisterNavigation = false;

    public static function getEloquentQuery(): Builder
    {
        return app(ProgrammeAccessService::class)->visibleQuery()->with('matiere');
    }

    public static function canViewAny(): bool
    {
        return app(ProgrammeAccessService::class)->autorise();
    }

    public static function canView(Model $record): bool
    {
        return static::getEloquentQuery()->whereKey($record->id)->exists();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('titre')->label('Programme'), TextInput::make('version')->label('Version'), TextInput::make('analyse_statut')->label('Analyse'), TextInput::make('statut')->label('Validation'),
            Placeholder::make('structure')->label('Structure pédagogique')->content(fn (?Programme $record) => $record?->chapitres()->get()->map(fn ($e) => ($e->parent_id ? '    • ' : '').$e->titre)->implode("\n") ?: 'Analyse en attente.'),
        ]);
    }

    public static function importAction(): Action
    {
        return AmbassadorsDesign::goldenFormModal(Action::make('importerProgramme')->label('Importer mon programme')->icon('heroicon-o-arrow-up-tray'))
            ->visible(fn () => app(ProgrammeAccessService::class)->enseignantId() !== null)
            ->schema([
                Select::make('contexte')->label('Mon offre pédagogique')->options(fn () => array_map(fn ($c) => $c['label'], app(ProgrammeAccessService::class)->offres(app(AnneeScolaireServiceContract::class)->getAnneeCouranteId())))->searchable()->required(),
                FileUpload::make('fichier')->label('Programme PDF ou Word')->acceptedFileTypes(['application/pdf', 'application/zip', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])->maxSize(5120)->storeFiles(false)->required(),
            ])->action(function (array $data): void {
                [$classe,$offre] = array_pad(explode(':', $data['contexte']), 2, 0);
                app(ProgramImportService::class)->importer(app(AnneeScolaireServiceContract::class)->getAnneeCouranteId(), (int) $classe, (int) $offre, $data['fichier']);
                Notification::make()->title('Programme importé : analyse en attente')->success()->send();
            });
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure(AmbassadorsDesign::configureTable($table, null)->columns([
            TextColumn::make('titre')->label('Offre / programme')->searchable(), TextColumn::make('version')->label('Version')->sortable(),
            TextColumn::make('analyse_statut')->label('Analyse')->badge()->formatStateUsing(fn ($state) => match ($state) {
                'analyse_en_attente' => 'En attente','analyse_en_cours' => 'En cours','a_valider' => 'À corriger / valider','valide' => 'Validé','echec_analyse' => 'Échec : relance possible',default => 'Programme historique'
            }),
            TextColumn::make('statut')->label('Validation')->badge(), TextColumn::make('importe_le')->label('Importé le')->dateTime(),
        ])->filters([SelectFilter::make('annee_scolaire_id')->label('Année')->options(fn () => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())->pluck('libelle', 'id'))->default(fn () => app(AnneeScolaireServiceContract::class)->getAnneeCouranteId())])
            ->recordActions([
                ViewAction::make(),
                AmbassadorsDesign::goldenFormModal(Action::make('structure')->label('Corriger / valider'))
                    ->visible(fn (Programme $record) => $record->analyse_statut === 'a_valider' && (int) $record->enseignant_id === app(ProgrammeAccessService::class)->enseignantId())
                    ->fillForm(fn (Programme $record) => ['elements' => app(ProgramStructureService::class)->proposition($record), 'valider' => false])
                    ->schema([
                        Repeater::make('elements')->label('Structure détectée')->minItems(1)->maxItems(500)->reorderable()->schema([
                            TextInput::make('titre')->label('Titre')->required()->maxLength(200),
                            Select::make('type')->label('Type')->options(['chapitre' => 'Chapitre', 'unite' => 'Unité / domaine', 'sequence' => 'Séquence', 'lecon' => 'Leçon', 'objectif' => 'Objectif', 'competence' => 'Compétence', 'activite' => 'Activité'])->required(),
                            Select::make('parent_index')->label('Élément parent')->options(fn (Get $get) => collect(array_values($get('../../elements') ?? []))->mapWithKeys(fn ($e, $i) => [$i => $e['titre'] ?? 'Élément '.($i + 1)]))->helperText('Choisissez un élément placé avant celui-ci. Laissez vide pour un élément principal.'),
                            TextInput::make('volume_horaire_prevu')->label('Heures prévues')->numeric()->minValue(0.01), Textarea::make('contenu')->label('Objectifs / contenu'),
                        ])->columnSpanFull(), Toggle::make('valider')->label('J’ai vérifié cette structure et je valide cette version')->default(false),
                    ])->action(fn (Programme $record, array $data) => app(ProgramStructureService::class)->enregistrer($record, $data['elements'], (bool) $data['valider'])),
                Action::make('relancer')->label('Relancer l’analyse')->visible(fn (Programme $record) => $record->analyse_statut === 'echec_analyse' && (int) $record->enseignant_id === app(ProgrammeAccessService::class)->enseignantId())->action(fn (Programme $record) => app(ProgramImportService::class)->relancer($record)),
                Action::make('original')->label('Fichier original')->visible(fn (Programme $record) => $record->document_source_id && ((int) $record->enseignant_id === app(ProgrammeAccessService::class)->enseignantId() || Auth::user()?->hasRole('Fondateur')))->url(fn (Programme $record) => app(DocumentServiceContract::class)->getUrlTelechargement($record->document_source_id))->openUrlInNewTab(),
            ])->defaultSort('id', 'desc')->poll('15s')->emptyStateHeading('Aucun programme pour cette année')->emptyStateDescription('Importez votre programme depuis une offre autorisée. Chaque nouvel import conserve la version précédente.'), static::class);
    }

    public static function getPages(): array
    {
        return ['index' => ProgrammeResource\Pages\ListProgrammes::route('/')];
    }
}
