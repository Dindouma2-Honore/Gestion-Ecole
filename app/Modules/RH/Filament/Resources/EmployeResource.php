<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Filament\Support\RhActions;
use App\Modules\RH\Models\Candidature;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PosteAdministratif;
use App\Modules\Scolarite\Contracts\AcademicStructureCatalogContract;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class EmployeResource extends Resource
{
    protected static ?string $model = Employe::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Employé';

    protected static ?string $pluralModelLabel = 'Personnel';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Candidature acceptée')
                ->description('Sélectionnez une personne dont le dossier a été accepté dans le menu Recrutement.')
                ->visibleOn('create')
                ->compact()
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Select::make('candidature_id')
                        ->label('Personne à intégrer')
                        ->options(fn (): array => DB::table('candidatures')
                            ->where('statut', 'retenue')
                            ->whereNull('employe_id')
                            ->orderBy('nom')
                            ->orderBy('prenom')
                            ->get()
                            ->mapWithKeys(fn (object $candidature): array => [
                                $candidature->id => trim($candidature->nom.' '.$candidature->prenom).' — '.$candidature->reference,
                            ])->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->dehydrated()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $candidature = DB::table('candidatures')->where('statut', 'retenue')->whereNull('employe_id')->find($state);
                            if (! $candidature) {
                                return;
                            }
                            $set('nom', $candidature->nom);
                            $set('prenom', $candidature->prenom);
                            $set('email', $candidature->email);
                            $set('telephone', $candidature->telephone);
                            $set('poste_administratif_id', PosteAdministratif::query()->where('nom', $candidature->poste_souhaite)->value('id'));
                            $set('role_id', Role::query()->where('name', $candidature->poste_souhaite)->value('id'));
                        })
                        ->helperText('Seules les candidatures acceptées et pas encore intégrées sont proposées.'),
                    View::make('rh::filament.forms.candidature-summary')
                        ->viewData(fn (Get $get): array => [
                            'record' => filled($get('candidature_id'))
                                ? Candidature::query()->find($get('candidature_id'))
                                : null,
                        ])
                        ->visible(fn (Get $get): bool => filled($get('candidature_id'))),
                ]),
            Section::make('Identité et accès')
                ->description('Les informations d’identité proviennent du dossier de recrutement.')
                ->compact()
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    TextInput::make('matricule')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Généré automatiquement à l’embauche')
                        ->visibleOn('edit'),
                    TextInput::make('nom')
                        ->required()
                        ->readOnly(fn (string $operation): bool => $operation === 'create')
                        ->maxLength(255),
                    TextInput::make('prenom')
                        ->required()
                        ->readOnly(fn (string $operation): bool => $operation === 'create')
                        ->maxLength(255),
                    DatePicker::make('date_naissance'),
                    Select::make('sexe')
                        ->options([
                            'M' => 'Masculin',
                            'F' => 'Féminin',
                        ]),
                    TextInput::make('telephone')
                        ->tel()
                        ->maxLength(20),
                    TextInput::make('email')
                        ->email()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->maxLength(255),
                    FileUpload::make('photo')
                        ->image()
                        ->directory('personnel/photos'),
                    Select::make('role_id')->label('Rôle d’accès applicatif')->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->required()->searchable()->preload()
                        ->helperText('Le rôle définit le niveau d’accès applicatif. Le poste professionnel est défini séparément par la fonction administrative.'),
                ]),
            Section::make('Responsabilités et contrat')
                ->description('Choisissez la formule de rémunération, puis renseignez uniquement les montants correspondants.')
                ->visibleOn('create')
                ->compact()
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Select::make('formule_paie')
                        ->label('Mode de rémunération')
                        ->options([
                            'fixe' => 'Salaire de base',
                            'horaire' => 'Taux horaire × heures réalisées',
                            'mixte' => 'Salaire de base + rémunération horaire',
                        ])
                        ->default('fixe')
                        ->required()
                        ->live(),
                    Select::make('type_contrat')
                        ->label('Type de contrat')
                        ->options([
                            'CDI' => 'CDI',
                            'CDD' => 'CDD',
                            'Stage' => 'Stage',
                            'Prestataire' => 'Prestataire / Vacataire',
                        ])->required(),
                    Select::make('grille_salariale_id')
                        ->label('Ligne de la grille salariale')
                        ->options(fn (): array => DB::table('grilles_salariales as g')->join('paliers_anciennete as p', 'p.id', '=', 'g.palier_anciennete_id')->where('g.actif', true)->where('p.anciennete_min_mois', 0)->select(['g.id', 'g.base_calcul', 'g.salaire_base', 'g.taux_horaire', 'p.nom'])->get()->mapWithKeys(fn (object $ligne): array => [$ligne->id => $ligne->nom.' — '.$ligne->base_calcul.' — '.number_format((float) $ligne->salaire_base, 0, ',', ' ').' / '.number_format((float) $ligne->taux_horaire, 0, ',', ' ').' FCFA'])->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->nullable()
                        ->helperText('Facultatif tant que la grille n’est pas encore configurée.'),
                    DatePicker::make('date_debut_contrat')->label('Début du contrat')->default(now())->required(),
                    DatePicker::make('date_fin_contrat')->label('Fin du contrat'),
                    TextInput::make('salaire_base')
                        ->label('Salaire de base')->numeric()->minValue(0)->prefix('FCFA')
                        ->visible(fn (Get $get): bool => in_array($get('formule_paie'), ['fixe', 'mixte'], true))
                        ->required(fn (Get $get): bool => in_array($get('formule_paie'), ['fixe', 'mixte'], true) && ! $get('grille_salariale_id'))
                        ->helperText('Laissez vide lorsqu’une ligne de grille salariale est sélectionnée.'),
                    TextInput::make('taux_horaire')
                        ->label('Taux horaire')->numeric()->minValue(0)->prefix('FCFA')
                        ->visible(fn (Get $get): bool => in_array($get('formule_paie'), ['horaire', 'mixte'], true))
                        ->required(fn (Get $get): bool => in_array($get('formule_paie'), ['horaire', 'mixte'], true) && ! $get('grille_salariale_id'))
                        ->helperText('Laissez vide lorsqu’une ligne de grille salariale est sélectionnée.'),
                ]),
            Section::make('Affectation')
                ->compact()
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Select::make('poste_administratif_id')
                        ->label('Fonction administrative')
                        ->relationship('posteAdministratif', 'nom')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->createOptionForm([
                            TextInput::make('nom')->label('Nom de la fonction')->required()->maxLength(255)->unique('postes_administratifs', 'nom'),
                            Textarea::make('description')->label('Description'),
                            Toggle::make('actif')->label('Fonction active')->default(true),
                        ])
                        ->helperText('Facultatif — choisissez une fonction existante ou créez-en une nouvelle.'),
                    Textarea::make('motif_changement_poste')
                        ->label('Motif du changement de poste')
                        ->visibleOn('edit')
                        ->required(fn (Get $get, ?Employe $record): bool => (int) $get('poste_administratif_id') !== (int) $record?->poste_administratif_id)
                        ->helperText('Obligatoire en cas de changement de fonction administrative.'),
                    TextInput::make('poste')
                        ->label('Poste actuel')
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit'),
                    TextInput::make('departement')
                        ->maxLength(100),
                    DatePicker::make('date_embauche')
                        ->default(now())
                        ->required(),
                    Select::make('niveau_id')
                        ->label('Niveau d\'enseignement')
                        ->options(fn () => collect(app(AcademicStructureCatalogContract::class)->niveaux())->pluck('name', 'id')->all())
                        ->nullable(),
                    Select::make('categories')
                        ->label('Catégories libres')
                        ->relationship('categories', 'nom', fn ($query) => $query->where('actif', true))
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->nullable()
                        ->helperText('Facultatif — ex. Vacataire, Permanent, Marié ou Célibataire. Sans effet automatique sur la paie.'),
                    Select::make('palier_anciennete_id')
                        ->label('Palier d’ancienneté calculé')
                        ->relationship('categorieAnciennete', 'nom')
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit'),
                    Select::make('statut')
                        ->options([
                            'actif' => 'Actif',
                            'suspendu' => 'Suspendu',
                            'en_conge' => 'En congé',
                            'demissionne' => 'Démissionné',
                            'licencie' => 'Licencié',
                        ])
                        ->default('actif')
                        ->visibleOn('edit')
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        AmbassadorsDesign::configureTable($table, null);
        $livewire = $table->getLivewire();
        $cards = $livewire instanceof EmployeResource\Pages\ListEmployes
            && method_exists($livewire, 'usesCardLayout')
            && $livewire->usesCardLayout();

        return $table
            ->columns($cards ? [
                Stack::make([
                    ViewColumn::make('carte_personnel')
                        ->label('Membre du personnel')
                        ->view('rh::filament.tables.employe-card')
                        ->searchable(['matricule', 'nom', 'prenom', 'poste', 'departement', 'email', 'telephone']),
                ]),
            ] : [
                TextColumn::make('matricule')->width('13%')->sortable()->searchable()->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('nom_complet')->label('Personnel')->width('25%')->grow()->sortable(['nom', 'prenom'])->searchable(['nom', 'prenom'])->weight('bold')->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('role.name')->label('Rôle')->width('13%')->badge()->placeholder('Non attribué')->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('posteAdministratif.nom')->label('Fonction')->width('18%')->placeholder('Aucune')->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('contratPrincipal.categorie_paie')
                    ->label('Rémunération')
                    ->width('16%')
                    ->formatStateUsing(fn (?string $state, Employe $record): string => match ($state) {
                        'fixe' => number_format((float) $record->contratPrincipal?->salaire_base, 0, ',', ' ').' FCFA',
                        'horaire' => number_format((float) $record->contratPrincipal?->taux_horaire, 0, ',', ' ').' FCFA/h',
                        'mixte' => 'Fixe + horaire',
                        default => 'Non définie',
                    }),
                TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'actif' => 'success',
                        'en_conge' => 'warning',
                        'suspendu' => 'danger',
                        'demissionne', 'licencie' => 'gray',
                        default => 'primary',
                    }),
            ])
            ->contentGrid($cards ? [
                'default' => 1,
                'sm' => 2,
                'md' => 2,
                'lg' => 3,
            ] : null)
            ->actions([
                ActionGroup::make([
                    AmbassadorsDesign::goldenFormModal(Action::make('activer_acces'))
                        ->label(fn (Employe $record): string => $record->user_id ? 'Réactiver l’accès' : 'Créer l’accès')
                        ->icon('heroicon-o-key')
                        ->color('success')
                        ->visible(fn (Employe $record): bool => ! $record->user_id || $record->user?->statut !== 'actif')
                        ->schema([
                            TextInput::make('email')->email()->required()->default(fn (Employe $record): ?string => $record->email ?? $record->user?->email),
                            Select::make('role_id')->label('Rôle d’accès applicatif')->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())->required()->default(fn (Employe $record): ?int => $record->role_id),
                        ])
                        ->requiresConfirmation()->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                        ->modalHeading('Donner accès au système')
                        ->action(function (Employe $record, array $data): void {
                            try {
                                $resultat = app(EmployeServiceContract::class)->creerAcces($record->id, $data['email'], (int) $data['role_id']);
                                $message = $resultat['mot_de_passe_temporaire']
                                    ? 'Compte créé. Mot de passe temporaire : '.$resultat['mot_de_passe_temporaire']
                                    : 'Le compte existant a été rattaché et réactivé.';
                                Notification::make()->success()->title('Accès opérationnel')->body($message)->persistent()->send();
                            } catch (\Throwable $e) {
                                Notification::make()->danger()->title('Erreur lors de l’attribution de l’accès')->body($e->getMessage())->send();
                            }
                        }),
                    RhActions::withRecordControls(Action::make('view'))
                        ->label('Voir')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->modalHeading(fn (Employe $record): string => 'Informations complètes — '.$record->nom_complet)
                        ->modalWidth('none')->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes())
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fermer')
                        ->modalContent(fn (Employe $record) => view('rh::filament.modals.employe-details', ['record' => $record->loadMissing(['role', 'posteAdministratif', 'contratPrincipal', 'categories', 'diplomes', 'sanctions', 'historiquePostes'])])),
                    RhActions::edit()->label('Modifier')->icon('heroicon-o-pencil-square'),
                    RhActions::delete()->label('Supprimer')->icon('heroicon-o-trash'),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->color('gray')
                    ->tooltip('Actions'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informations complètes du personnel')->schema([
                Grid::make(3)->schema([
                    ImageEntry::make('photo')->label('Photo')->disk('public')->circular(),
                    TextEntry::make('nom_complet')->label('Nom complet')->weight('bold'),
                    TextEntry::make('matricule')->label('Matricule'),
                    TextEntry::make('date_naissance')->label('Date de naissance')->date('d/m/Y')->placeholder('Non renseignée'),
                    TextEntry::make('sexe')->label('Sexe')->formatStateUsing(fn (?string $state): string => match ($state) {
                        'F' => 'Féminin', 'M' => 'Masculin', default => 'Non renseigné'
                    }),
                    TextEntry::make('telephone')->label('Téléphone')->placeholder('Non renseigné'),
                    TextEntry::make('email')->label('E-mail')->placeholder('Non renseigné'),
                    TextEntry::make('role.name')->label('Rôle')->badge()->placeholder('Non attribué'),
                    TextEntry::make('posteAdministratif.nom')->label('Fonction administrative')->placeholder('Aucune'),
                    TextEntry::make('departement')->label('Département')->placeholder('Non renseigné'),
                    TextEntry::make('niveau_label')->state(fn ($record) => $record->niveau?->name)->label('Niveau d’enseignement')->placeholder('Non renseigné'),
                    TextEntry::make('date_embauche')->label('Date d’embauche')->date('d/m/Y'),
                    TextEntry::make('statut')->label('Statut')->badge(),
                    TextEntry::make('contratPrincipal.type')->label('Type de contrat')->placeholder('Non défini'),
                    TextEntry::make('contratPrincipal.categorie_paie')->label('Mode de rémunération')->placeholder('Non défini'),
                    TextEntry::make('contratPrincipal.salaire_base')->label('Salaire de base')->money('XAF')->placeholder('0 FCFA'),
                    TextEntry::make('contratPrincipal.taux_horaire')->label('Taux horaire')->money('XAF')->placeholder('0 FCFA'),
                ]),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => EmployeResource\Pages\ListEmployes::route('/'),
            'create' => EmployeResource\Pages\CreateEmploye::route('/create'),
            'view' => EmployeResource\Pages\ViewEmploye::route('/{record}'),
            'edit' => EmployeResource\Pages\EditEmploye::route('/{record}/edit'),
        ];
    }
}
