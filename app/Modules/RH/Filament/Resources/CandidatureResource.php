<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Events\CandidateApplicationAccepted;
use App\Modules\RH\Events\CandidateApplicationRejected;
use App\Modules\RH\Filament\Resources\CandidatureResource\Pages;
use App\Modules\RH\Filament\Support\RhActions;
use App\Modules\RH\Models\Candidature;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PosteAdministratif;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class CandidatureResource extends Resource
{
    protected static ?string $model = Candidature::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Candidature';

    protected static ?string $pluralModelLabel = 'Recrutement';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identité du candidat')
                ->description('Renseignez les coordonnées utilisées pour le suivi du recrutement.')
                ->icon('heroicon-o-user')
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    TextInput::make('reference')
                        ->label('Référence')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Générée automatiquement'),
                    TextInput::make('nom')
                        ->label('Nom')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('prenom')
                        ->label('Prénom')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('Adresse e-mail')
                        ->email()
                        ->maxLength(255),
                    TextInput::make('telephone')
                        ->label('Téléphone')
                        ->tel()
                        ->maxLength(30),
                ]),
            Section::make('Dossier de recrutement')
                ->description('Précisez le poste visé et ajoutez les documents utiles.')
                ->icon('heroicon-o-briefcase')
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Select::make('poste_souhaite')
                        ->label('Poste recherché')
                        ->options(function (): array {
                            $postes = PosteAdministratif::query()->where('actif', true)->orderBy('nom')->pluck('nom', 'nom')->all();

                            return ! empty($postes) ? $postes : Role::query()->orderBy('name')->pluck('name', 'name')->all();
                        })
                        ->required()
                        ->searchable()
                        ->preload(),
                    Select::make('statut')
                        ->label('Statut')
                        ->options(self::statuts())
                        ->default('nouvelle')
                        ->required(),
                    FileUpload::make('cv_path')
                        ->label('CV (PDF)')
                        ->directory('personnel/candidatures')
                        ->acceptedFileTypes(['application/pdf'])
                        ->columnSpanFull(),
                    DateTimePicker::make('date_entretien')
                        ->label('Date de l’entretien')
                        ->visibleOn('edit'),
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        AmbassadorsDesign::configureTable($table, null);

        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nom_complet')
                    ->label('Candidat')
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom'])
                    ->limit(24)
                    ->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('poste_souhaite')
                    ->label('Poste')
                    ->badge()
                    ->searchable()
                    ->limit(14)
                    ->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('created_at')->label('Date')->date('d/m/y')->sortable(),
                TextColumn::make('statut')->badge()->formatStateUsing(fn (string $state): string => self::statuts()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'retenue', 'embauche' => 'success', 'rejetee' => 'danger',
                        'preselectionnee', 'entretien' => 'warning', default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->recordActionsColumnLabel('Actions')
            ->actions([
                ActionGroup::make([
                    RhActions::view()
                        ->label('Voir')
                        ->modalHeading(fn (Candidature $record): string => 'Dossier de recrutement — '.$record->nom_complet)
                        ->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes())
                        ->modalContent(fn (Candidature $record) => view('rh::filament.modals.candidature-details', ['record' => $record])),
                    Action::make('preselectionner')->label('Présélectionner')->icon('heroicon-o-check')
                        ->visible(fn (Candidature $record): bool => $record->statut === 'nouvelle')
                        ->action(fn (Candidature $record) => self::changerStatut($record, 'preselectionnee')),
                    Action::make('retenir')->label('Accepter le dossier')->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn (Candidature $record): bool => in_array($record->statut, ['nouvelle', 'preselectionnee', 'entretien'], true))
                        ->requiresConfirmation()->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                        ->action(fn (Candidature $record) => self::changerStatut($record, 'retenue')),
                    Action::make('embaucher')->label('Intégrer au personnel')->icon('heroicon-o-user-plus')->color('success')
                        ->visible(fn (Candidature $record): bool => $record->statut === 'retenue' && EmployeResource::canCreate())
                        ->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes())
                        ->schema(fn (Schema $schema): Schema => EmployeResource::form($schema->model(Employe::class)->operation('create')))
                        ->fillForm(fn (Candidature $record): array => [
                            'candidature_id' => $record->id, 'nom' => $record->nom, 'prenom' => $record->prenom,
                            'email' => $record->email, 'telephone' => $record->telephone,
                            'poste_administratif_id' => PosteAdministratif::query()->where('nom', $record->poste_souhaite)->value('id'),
                            'poste' => $record->poste_souhaite,
                            'role_id' => Role::where('name', $record->poste_souhaite)->value('id'),
                            'statut' => 'actif', 'date_debut_contrat' => now()->toDateString(),
                        ])
                        ->action(function (array $data): void {
                            abort_unless(EmployeResource::canCreate(), 403);
                            try {
                                $resultat = app(EmployeServiceContract::class)->embaucher($data);
                                $pwd = ! empty($resultat['mot_de_passe_temporaire']) ? " Mot de passe temporaire : {$resultat['mot_de_passe_temporaire']}" : '';
                                Notification::make()
                                    ->title('Candidat embauché avec succès')
                                    ->body("Le membre du personnel a été créé.{$pwd}")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Erreur lors de l’embauche')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('rejeter')->label(fn (Candidature $record): string => $record->statut === 'retenue' ? 'Révoquer l’acceptation' : 'Rejeter le dossier')->color('danger')->requiresConfirmation()->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                        ->visible(fn (Candidature $record): bool => ! in_array($record->statut, ['rejetee', 'embauche'], true))
                        ->action(fn (Candidature $record) => self::changerStatut($record, 'rejetee')),
                    RhActions::edit()->label('Modifier'),
                    RhActions::delete()->label('Supprimer'),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->color('gray')
                    ->tooltip('Actions'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCandidatures::route('/'),
            'create' => Pages\CreateCandidature::route('/create'),
            'edit' => Pages\EditCandidature::route('/{record}/edit'),
        ];
    }

    public static function statuts(): array
    {
        return ['nouvelle' => 'Nouvelle', 'preselectionnee' => 'Présélectionnée', 'entretien' => 'Entretien planifié', 'retenue' => 'Retenue', 'rejetee' => 'Rejetée', 'embauche' => 'Embauchée'];
    }

    private static function changerStatut(Candidature $record, string $statut): void
    {
        $record->update(['statut' => $statut, 'evaluee_par' => Auth::id()]);

        if ($statut === 'retenue') {
            event(new CandidateApplicationAccepted(
                candidatureId: $record->id,
                candidateName: $record->nom_complet,
                position: $record->poste_souhaite,
                phone: $record->telephone,
            ));
        }

        if ($statut === 'rejetee') {
            event(new CandidateApplicationRejected(
                candidatureId: $record->id,
                candidateName: $record->nom_complet,
                position: $record->poste_souhaite,
                phone: $record->telephone,
            ));
        }

        Notification::make()->success()->title('Candidature mise à jour')->send();
    }
}
