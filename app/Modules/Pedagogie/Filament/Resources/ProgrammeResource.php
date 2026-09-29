<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Models\User;
use App\Modules\Logistique\Contracts\InfrastructureServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\ProgrammeResource\Pages;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class ProgrammeResource extends Resource
{
    protected static ?string $model = Programme::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Programmes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('matiere_id')
                    ->label('Matière')
                    ->options(Matiere::where('actif', true)->pluck('nom', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('annee_scolaire_id')
                    ->label('Année scolaire')
                    ->options(fn () => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())
                        ->mapWithKeys(fn ($a) => [data_get($a, 'id') => data_get($a, 'libelle', data_get($a, 'nom', '#' . data_get($a, 'id')))]))
                    ->live()
                    ->default(fn () => app(AnneeScolaireServiceContract::class)->getAnneeCouranteId())
                    ->searchable()
                    ->required(),

                Select::make('classe_id')
                    ->label('Classe')
                    ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                        $anneeScolaireId = (int) ($get('annee_scolaire_id')
                            ?: app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());

                        return collect(app(ClasseServiceInterface::class)->getToutesLesClasses($anneeScolaireId))
                            ->pluck('nom', 'id');
                    })
                    ->searchable()
                    ->required(),

                Select::make('niveau_id')
                    ->label('Niveau')
                    ->options(fn () => collect(app(ParametrageServiceContract::class)->getTousLesNiveaux())->pluck('nom', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('source')
                    ->label('Source du programme')
                    ->options([
                        'officiel' => 'Programme Officiel',
                        'enseignant' => 'Programme Enseignant',
                    ])
                    ->default('officiel')
                    ->required(),

                Select::make('enseignant_id')
                    ->label('Enseignant')
                    ->options(fn () => User::role('Enseignant')->pluck('name', 'id'))
                    ->searchable(),

                TextInput::make('salle_id')
                    ->label('ID Salle habituelle (déduit)')
                    ->numeric()
                    ->disabled()
                    ->helperText('Déduit automatiquement de l’emploi du temps de l’enseignant — non modifiable ici.'),

                TextInput::make('titre')
                    ->label('Titre')
                    ->required()
                    ->maxLength(200),

                Textarea::make('description')
                    ->label('Description'),

                Select::make('statut')
                    ->label('Statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'soumis' => 'En attente de validation',
                        'valide' => 'Validé',
                        'rejete' => 'Rejeté',
                    ])
                    ->default('valide')
                    ->disabled()
                    ->dehydrated(),

                Textarea::make('motif_rejet')
                    ->label('Motif de rejet')
                    ->disabled()
                    ->visible(fn ($record) => $record && $record->statut === 'rejete'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('titre')
                    ->label('Titre')
                    ->searchable(),

                Tables\Columns\TextColumn::make('matiere.nom')
                    ->label('Matière'),

                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color(fn (string $state) => $state === 'enseignant' ? 'info' : 'gray'),

                Tables\Columns\TextColumn::make('classe_id')
                    ->label('Classe (ID)'),

                Tables\Columns\TextColumn::make('niveau_id')
                    ->label('Niveau')
                    ->formatStateUsing(fn ($state) => data_get(
                        collect(app(ParametrageServiceContract::class)->getTousLesNiveaux())->firstWhere('id', $state),
                        'nom',
                        "#{$state}"
                    )),

                Tables\Columns\TextColumn::make('salle_id')
                    ->label('Salle')
                    ->formatStateUsing(fn ($state) => $state
                        ? data_get(
                            collect(app(InfrastructureServiceInterface::class)->getSallesDisponibles())->firstWhere('id', $state),
                            'nom',
                            "#{$state}"
                        )
                        : '—'),

                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'valide' => 'success',
                        'soumis' => 'warning',
                        'rejete' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                EditAction::make(),

                Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Programme $record) => $record->statut === 'soumis')
                    ->successNotificationTitle(null)
                    ->action(function (Programme $record): void {
                        try {
                            /** @var ProgrammeServiceInterface $service */
                            $service = app(ProgrammeServiceInterface::class);
                            $service->validerProgramme($record->id, Auth::id() ?? 1);

                            Notification::make()
                                ->title('Complete successfully')
                                ->body("Le programme #{$record->id} a été validé avec succès.")
                                ->success()
                                ->duration(6000)
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Échec de la validation')
                                ->body('Erreur : ' . $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Action::make('rejeter')
                    ->label('Rejeter')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Programme $record) => $record->statut === 'soumis')
                    ->successNotificationTitle(null)
                    ->form([
                        Textarea::make('motif')
                            ->label('Motif du rejet (obligatoire)')
                            ->required()
                            ->minLength(3),
                    ])
                    ->action(function (Programme $record, array $data): void {
                        try {
                            /** @var ProgrammeServiceInterface $service */
                            $service = app(ProgrammeServiceInterface::class);
                            $service->rejeterProgramme($record->id, (string) $data['motif']);

                            Notification::make()
                                ->title('Programme rejeté')
                                ->body("Le programme #{$record->id} a été rejeté.")
                                ->warning()
                                ->duration(6000)
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Échec du rejet')
                                ->body('Erreur : ' . $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->headerActions([
                // Soumission par l'enseignant : programme annuel en PDF/Word (source
                // du programme), avec structure de chapitres optionnelle en plus du
                // fichier. Passe par ProgrammeServiceInterface::soumettreProgramme(),
                // qui crée le programme au statut "soumis" en attente de validation
                // par le Directeur (voir actions "valider"/"rejeter" ci-dessus).
                Action::make('soumettreProgramme')
                    ->label('Soumettre mon programme')
                    ->icon('heroicon-o-document-plus')
                    ->color('primary')
                    ->modalHeading('Soumettre mon programme pédagogique')
                    ->modalSubmitActionLabel('Soumettre le programme')
                    ->form([
                        Select::make('matiere_id')
                            ->label('Matière')
                            ->options(
                                Matiere::query()
                                    ->where('actif', true)
                                    ->orderBy('nom')
                                    ->pluck('nom', 'id')
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('annee_scolaire_id')
                            ->label('Année scolaire')
                            ->options(fn () => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())
                                ->mapWithKeys(fn ($a) => [data_get($a, 'id') => data_get($a, 'libelle', data_get($a, 'nom', '#' . data_get($a, 'id')))]))
                            ->live()
                            ->default(fn () => app(AnneeScolaireServiceContract::class)->getAnneeCouranteId())
                            ->required(),

                        Select::make('classe_id')
                            ->label('Classe')
                            ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                $anneeScolaireId = (int) ($get('annee_scolaire_id')
                                    ?: app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());

                                return collect(app(ClasseServiceInterface::class)->getToutesLesClasses($anneeScolaireId))
                                    ->pluck('nom', 'id');
                            })
                            ->searchable()
                            ->required(),

                        FileUpload::make('fichier_source')
                            ->label('Programme annuel (PDF ou Word)')
                            ->helperText('Formats acceptés : PDF, Word (.docx) ou Excel (.xlsx) pour la structure des chapitres.')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->maxSize(5120)
                            ->required()
                            ->storeFiles(false)
                            ->downloadable()
                            ->openable(),

                        Repeater::make('chapitres')
                            ->label('Structure des chapitres')
                            ->schema([
                                TextInput::make('titre')
                                    ->label('Titre du chapitre')
                                    ->required(),

                                TextInput::make('ordre')
                                    ->label('Ordre')
                                    ->numeric()
                                    ->default(1)
                                    ->required(),

                                Textarea::make('objectifs_pedagogiques')
                                    ->label('Objectifs pédagogiques')
                                    ->rows(3),

                                Select::make('periode_prevue_id')
                                    ->label('Période prévue')
                                    ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                        $anneeScolaireId = (int) ($get('../../annee_scolaire_id')
                                            ?: app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());

                                        return collect(app(AnneeScolaireServiceContract::class)->getPeriodes($anneeScolaireId))
                                            ->pluck('nom', 'id');
                                    })
                                    ->searchable(),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->addActionLabel('Ajouter un chapitre'),
                    ])
                    ->action(function (array $data): void {
                        $enseignantId = Auth::id();

                        if ($enseignantId === null) {
                            Notification::make()
                                ->title('Utilisateur non authentifié')
                                ->body('Vous devez être connecté pour soumettre un programme.')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        $fichier = $data['fichier_source'] ?? null;

                        if ($fichier instanceof TemporaryUploadedFile) {
                            $fichier = new UploadedFile(
                                $fichier->getRealPath(),
                                $fichier->getClientOriginalName(),
                                $fichier->getMimeType(),
                                null,
                                true
                            );
                        }

                        if (! $fichier instanceof UploadedFile) {
                            Notification::make()
                                ->title('Fichier invalide')
                                ->body('Le fichier source n’a pas pu être récupéré correctement.')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        try {
                            /** @var ProgrammeServiceInterface $service */
                            $service = app(ProgrammeServiceInterface::class);

                            $service->soumettreProgramme(
                                enseignantId: $enseignantId,
                                matiereId: (int) $data['matiere_id'],
                                classeId: (int) $data['classe_id'],
                                anneeScolaireId: (int) $data['annee_scolaire_id'],
                                fichierSource: $fichier,
                                chapitres: $data['chapitres'] ?? [],
                            );

                            Notification::make()
                                ->title('Programme soumis')
                                ->body('Votre programme pédagogique a été soumis avec succès et est en attente de validation par le Directeur.')
                                ->success()
                                ->duration(6000)
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);

                            Notification::make()
                                ->title('Échec de la soumission')
                                ->body('Erreur : ' . $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProgrammes::route('/'),
            'create' => Pages\CreateProgramme::route('/create'),
            'edit' => Pages\EditProgramme::route('/{record}/edit'),
        ];
    }
}
