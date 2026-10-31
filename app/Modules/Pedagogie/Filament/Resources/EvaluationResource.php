<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\Pedagogie\Contracts\EvaluationServiceInterface;
use App\Modules\Pedagogie\Contracts\NoteServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\EvaluationResource\Pages;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class EvaluationResource extends Resource
{
    protected static ?string $model = Evaluation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Pédagogie';

    protected static ?string $navigationLabel = 'Évaluations';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('titre')->required()->maxLength(255),

            Select::make('matiere_id')
                ->label('Matière')
                ->relationship('matiere', 'nom')
                ->searchable()
                ->preload()
                ->disabled(fn (?Evaluation $record) => $record !== null && ($record->verrouillee || $record->notes()->exists()))
                ->required(),

            Select::make('classe_id')
                ->label('Classe')
                ->options(function () {
                    $anneeScolaireId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();

                    return collect(app(ClasseServiceInterface::class)->getToutesLesClasses($anneeScolaireId))
                        ->pluck('nom', 'id');
                })
                ->searchable()
                ->disabled(fn (?Evaluation $record) => $record !== null && ($record->verrouillee || $record->notes()->exists()))
                ->required(),

            Select::make('annee_scolaire_id')
                ->label('Année scolaire')
                ->options(fn (): array => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())->pluck('libelle', 'id')->all())
                ->default(fn (): int => app(AnneeScolaireServiceContract::class)->getAnneeCouranteId())
                ->required()
                ->live(),

            Select::make('periode_id')
                ->label('Période / séquence')
                ->options(fn (callable $get): array => $get('annee_scolaire_id')
                    ? collect(app(AnneeScolaireServiceContract::class)->getPeriodes((int) $get('annee_scolaire_id')))->pluck('libelle', 'id')->all()
                    : []),

            Select::make('type_evaluation')->label('Type')->options([
                'devoir' => 'Devoir', 'controle' => 'Contrôle', 'interrogation' => 'Interrogation',
                'composition' => 'Composition', 'examen' => 'Examen',
            ])->default('devoir')->required(),

            DatePicker::make('date_evaluation')->label('Date')->required(),
            TextInput::make('bareme')
                ->label('Barème')
                ->numeric()
                ->minValue(0.01)
                ->default(20)
                ->disabled(fn (?Evaluation $record) => $record !== null && ($record->verrouillee || $record->notes()->exists()))
                ->required(),
            TextInput::make('coefficient_evaluation')->label('Coefficient de l’évaluation')->numeric()->minValue(0.01)->default(1)->required(),

            // Sujet d'examen proposé par l'enseignant : reste en attente de
            // validation par le Directeur (statut "soumis") tant que l'action
            // "Valider" n'a pas été effectuée. Les notes ne peuvent être
            // saisies pour cette évaluation qu'une fois le sujet validé (voir
            // NoteService::enregistrer()).
            FileUpload::make('sujet')
                ->label("Sujet d'examen (PDF ou Word, optionnel)")
                ->helperText('Le sujet proposé sera soumis à la validation du Directeur avant que les notes ne puissent être saisies.')
                ->acceptedFileTypes([
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ])
                ->maxSize(5120)
                ->storeFiles(false)
                ->downloadable()
                ->openable()
                ->visibleOn('create'),

            Select::make('statut')
                ->label('Statut')
                ->options([
                    'soumis' => 'Soumis (en attente de validation)',
                    'valide' => 'Validé',
                    'rejete' => 'Rejeté',
                ])
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit'),

            Textarea::make('motif_rejet')
                ->label('Motif du rejet')
                ->disabled()
                ->dehydrated(false)
                ->visible(fn (?Evaluation $record) => $record?->statut === 'rejete'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table->columns([
            Tables\Columns\TextColumn::make('titre')->searchable(),
            Tables\Columns\TextColumn::make('matiere.nom')->label('Matière'),
            Tables\Columns\TextColumn::make('classe_id')->label('Classe #'),
            Tables\Columns\TextColumn::make('date_evaluation')->label('Date')->date()->sortable(),
            Tables\Columns\TextColumn::make('bareme')->label('Barème'),
            Tables\Columns\TextColumn::make('coefficient_evaluation')->label('Coefficient'),

            Tables\Columns\TextColumn::make('statut')
                ->label('Statut du sujet')
                ->badge()
                ->color(fn (string $state) => match ($state) {
                    'valide' => 'success',
                    'soumis' => 'warning',
                    'rejete' => 'danger',
                    default => 'gray',
                }),
        ])->actions([
            EditAction::make(),

            Action::make('valider')
                ->label('Valider le sujet')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Evaluation $record) => $record->statut === 'soumis' && (
                    Auth::user() === null || ! method_exists(Auth::user(), 'hasAnyRole') || Auth::user()->hasAnyRole([
                        'Directeur', 'Directrice', 'Fondateur', 'Super Admin', 'Admin', 'Direction', 'Coordinateur Pédagogique',
                    ])
                ))
                ->action(function (Evaluation $record): void {
                    try {
                        app(EvaluationServiceInterface::class)->validerSujet($record->id, Auth::id() ?? 1);

                        Notification::make()
                            ->title('Sujet validé')
                            ->body("Le sujet de l'évaluation #{$record->id} a été validé. Les notes peuvent maintenant être saisies.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Échec de la validation')
                            ->body('Erreur : '.$e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('rejeter')
                ->label('Rejeter le sujet')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Evaluation $record) => $record->statut === 'soumis' && (
                    Auth::user() === null || ! method_exists(Auth::user(), 'hasAnyRole') || Auth::user()->hasAnyRole([
                        'Directeur', 'Directrice', 'Fondateur', 'Super Admin', 'Admin', 'Direction', 'Coordinateur Pédagogique',
                    ])
                ))
                ->form([
                    Textarea::make('motif')
                        ->label('Motif du rejet (obligatoire)')
                        ->required()
                        ->minLength(3),
                ])
                ->action(function (Evaluation $record, array $data): void {
                    try {
                        app(EvaluationServiceInterface::class)->rejeterSujet($record->id, (string) $data['motif'], Auth::id());

                        Notification::make()
                            ->title('Sujet rejeté')
                            ->body("Le sujet de l'évaluation #{$record->id} a été rejeté.")
                            ->warning()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Échec du rejet')
                            ->body('Erreur : '.$e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('soumettreNotes')
                ->label('Soumettre les notes')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (Evaluation $record) => $record->statut === 'valide' && ! $record->verrouillee && $record->notes()->where('statut', '!=', 'valide')->exists())
                ->requiresConfirmation()
                ->modalHeading('Soumettre les notes pour approbation')
                ->modalDescription('Êtes-vous sûr de vouloir soumettre toutes les notes saisies pour cette évaluation ?')
                ->action(function (Evaluation $record): void {
                    try {
                        app(NoteServiceInterface::class)->soumettreNotes($record->id, Auth::id());

                        Notification::make()
                            ->title('Notes soumises')
                            ->body("Les notes de l'évaluation #{$record->id} ont été soumises pour approbation du Fondateur.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Échec de la soumission')
                            ->body('Erreur : '.$e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('approuverNotes')
                ->label('Approuver et geler')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn (Evaluation $record) => $record->statut === 'valide' && ! $record->verrouillee && (
                    Auth::user() === null || ! method_exists(Auth::user(), 'hasAnyRole') || Auth::user()->hasAnyRole(['Fondateur', 'Super Admin'])
                ))
                ->requiresConfirmation()
                ->modalHeading('Approuver et geler les notes')
                ->modalDescription('Attention : cette action fige définitivement les notes et verrouille l\'évaluation. Seul un déblocage exceptionnel du Fondateur pourra autoriser une modification ultérieure.')
                ->action(function (Evaluation $record): void {
                    try {
                        app(NoteServiceInterface::class)->approuverNotes($record->id, Auth::id() ?? 1);

                        Notification::make()
                            ->title('Notes approuvées et gelées')
                            ->body("Les notes de l'évaluation #{$record->id} sont désormais gelées.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Échec de l\'approbation')
                            ->body('Erreur : '.$e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ]), static::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvaluations::route('/'),
            'create' => Pages\CreateEvaluation::route('/create'),
            'edit' => Pages\EditEvaluation::route('/{record}/edit'),
        ];
    }
}
