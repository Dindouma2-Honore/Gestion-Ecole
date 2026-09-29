<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Models\Employe;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
            Section::make('Identité et accès')->schema([
                TextInput::make('matricule')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Généré automatiquement à l’embauche')
                    ->visibleOn('edit'),
                TextInput::make('nom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('prenom')
                    ->required()
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
                FileUpload::make('photo')->image()->directory('personnel/photos'),
                Select::make('role_id')->label('Poste')->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->required()->searchable()->preload()
                    ->helperText('Le poste correspond à un rôle existant et détermine les habilitations générales.'),
            ]),
            Section::make('Affectation')->schema([
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
                    ->helperText('Facultatif — choisissez une fonction FACILG existante ou créez-en une nouvelle.'),
                Textarea::make('motif_changement_poste')->label('Motif du changement de poste')->dehydrated(false)->visibleOn('edit'),
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
                    ->relationship('niveau', 'nom')
                    ->nullable(),
                Select::make('categories')
                    ->label('Catégories')
                    ->relationship('categories', 'nom', fn ($query) => $query->where('actif', true))
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->nullable()
                    ->helperText('Facultatif — permet de distinguer ou regrouper les membres du personnel.'),
                Select::make('categorie_anciennete_id')
                    ->label('Catégorie d’ancienneté')
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
            Section::make('Responsabilités et contrat')
                ->description('La formule de paie est déterminée automatiquement selon les responsabilités sélectionnées.')
                ->visibleOn('create')
                ->schema([
                    Toggle::make('responsabilite_fixe')
                        ->label('Responsabilité à salaire fixe')
                        ->helperText('Maternelle, primaire, secondaire, auxiliaire ou autre personnel permanent.')
                        ->default(true)
                        ->live(),
                    Toggle::make('responsabilite_horaire')
                        ->label('Responsabilité rémunérée à l’heure')
                        ->helperText('Vacations, enseignements ou autres responsabilités calculées selon les heures.')
                        ->default(false)
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
                        ->options(fn (): array => DB::table('grilles_salariales as g')->join('categories_personnel as c', 'c.id', '=', 'g.categorie_personnel_id')->where('g.actif', true)->where('c.anciennete_min_mois', 0)->select(['g.id', 'g.base_calcul', 'g.salaire_base', 'g.taux_horaire', 'c.nom'])->get()->mapWithKeys(fn (object $ligne): array => [$ligne->id => $ligne->nom.' — '.$ligne->base_calcul.' — '.number_format((float) $ligne->salaire_base, 0, ',', ' ').' / '.number_format((float) $ligne->taux_horaire, 0, ',', ' ').' FCFA'])->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->nullable()
                        ->helperText('Facultatif tant que la grille n’est pas encore configurée.'),
                    DatePicker::make('date_debut_contrat')->label('Début du contrat')->default(now())->required(),
                    DatePicker::make('date_fin_contrat')->label('Fin du contrat'),
                    DatePicker::make('periode_essai_fin')->label('Fin de la période d’essai'),
                    TextInput::make('salaire_base')
                        ->label('Salaire de base')->numeric()->minValue(0)->prefix('FCFA')
                        ->visible(fn (Get $get): bool => (bool) $get('responsabilite_fixe'))
                        ->required(fn (Get $get): bool => (bool) $get('responsabilite_fixe') && ! $get('grille_salariale_id'))
                        ->helperText('Laissez vide lorsqu’une ligne de grille salariale est sélectionnée.'),
                    TextInput::make('taux_horaire')
                        ->label('Taux horaire')->numeric()->minValue(0)->prefix('FCFA')
                        ->visible(fn (Get $get): bool => (bool) $get('responsabilite_horaire'))
                        ->required(fn (Get $get): bool => (bool) $get('responsabilite_horaire') && ! $get('grille_salariale_id'))
                        ->helperText('Laissez vide lorsqu’une ligne de grille salariale est sélectionnée.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
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
                TextColumn::make('matricule')->sortable()->searchable(),
                TextColumn::make('nom')->sortable()->searchable(),
                TextColumn::make('prenom')->sortable()->searchable(),
                TextColumn::make('poste')->sortable()->searchable(),
                TextColumn::make('departement')->sortable()->searchable(),
                TextColumn::make('categories.nom')->label('Catégories')->badge()->searchable(),
                TextColumn::make('date_embauche')->date()->sortable(),
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
                Action::make('activer_acces')
                    ->label(fn (Employe $record): string => $record->user_id ? 'Réactiver l’accès' : 'Créer l’accès')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->visible(fn (Employe $record): bool => ! $record->user_id || $record->user?->statut !== 'actif')
                    ->schema([
                        TextInput::make('email')->email()->required()->default(fn (Employe $record): ?string => $record->email),
                        Select::make('role_id')->label('Poste')->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())->required()->default(fn (Employe $record): ?int => $record->role_id),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Donner accès au système')
                    ->action(function (Employe $record, array $data): void {
                        $resultat = app(EmployeServiceContract::class)->creerAcces($record->id, $data['email'], (int) $data['role_id']);
                        $message = $resultat['mot_de_passe_temporaire']
                            ? 'Compte créé. Mot de passe temporaire : '.$resultat['mot_de_passe_temporaire']
                            : 'Le compte existant a été rattaché et réactivé.';
                        Notification::make()->success()->title('Accès opérationnel')->body($message)->persistent()->send();
                    }),
                ViewAction::make()->label('Voir'),
                EditAction::make()->label('Modifier'),
                DeleteAction::make()->label('Supprimer'),
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
