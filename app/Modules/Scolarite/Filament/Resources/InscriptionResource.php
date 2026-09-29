<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Filament\Support\ModuleAccess;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Scolarite\Support\ElevePhoto;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class InscriptionResource extends Resource
{
    protected static ?string $model = Inscription::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Inscriptions';

    protected static ?string $modelLabel = 'inscription';

    protected static ?string $pluralModelLabel = 'inscriptions';

    /**
     * Tout membre du personnel disposant d'un compte actif peut démarrer
     * une inscription. L'inscription est validée directement ici
     * (Scolarité) — elle ne dépend plus du module Finances tant que celui-ci
     * n'est pas livré.
     */
    public static function canCreate(): bool
    {
        return ModuleAccess::canStartRegistration(Auth::user());
    }

    /**
     * Formulaire volontairement minimal (élève + classe + année) : toute la
     * logique (matricule, capacité, double inscription) reste dans
     * InscriptionServiceInterface::inscrire(), jamais ici.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([Wizard::make([
                Step::make('Élève')->description('Identité de l’enfant')->schema([
                    Toggle::make('eleve_existant')
                        ->label('L’élève possède déjà un dossier')
                        ->live()
                        ->default(false)
                        ->afterStateUpdated(function ($state, callable $set): void {
                            $set('eleve_id', null);
                            $set('parent_existant', false);
                            $set('parent_id', null);
                        }),
                    Select::make('eleve_id')
                        ->label('Dossier élève')
                        ->options(fn () => Eleve::query()->orderBy('nom')->get()->mapWithKeys(fn (Eleve $e) => [
                            $e->id => trim("{$e->nom} {$e->prenom}").($e->matricule_permanent ? " — {$e->matricule_permanent}" : ''),
                        ]))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set): void {
                            if (! filled($state)) {
                                $set('parent_existant', false);
                                $set('parent_id', null);

                                return;
                            }

                            $eleve = Eleve::query()->with('parentsTuteurs')->find($state);
                            $parent = $eleve?->parentsTuteurs
                                ->first(fn (ParentTuteur $parent): bool => (bool) $parent->pivot?->responsable_paiement && filled($parent->email))
                                ?? $eleve?->parentsTuteurs->first(fn (ParentTuteur $parent): bool => filled($parent->email));

                            $set('parent_existant', $parent !== null);
                            $set('parent_id', $parent?->id);
                            $set('lien_parente', $parent?->pivot?->lien ?: 'tuteur');
                        })
                        ->visible(fn ($get) => (bool) $get('eleve_existant'))
                        ->required(fn ($get) => (bool) $get('eleve_existant')),
                    TextInput::make('eleve.nom')->label('Nom')->maxLength(100)->visible(fn ($get) => ! $get('eleve_existant'))->required(fn ($get) => ! $get('eleve_existant')),
                    TextInput::make('eleve.prenom')->label('Prénom')->maxLength(100)->visible(fn ($get) => ! $get('eleve_existant'))->required(fn ($get) => ! $get('eleve_existant')),
                    DatePicker::make('eleve.date_naissance')->label('Date de naissance')->visible(fn ($get) => ! $get('eleve_existant'))->required(fn ($get) => ! $get('eleve_existant')),
                    Select::make('eleve.sexe')->label('Sexe')->options(['M' => 'Masculin', 'F' => 'Féminin'])->visible(fn ($get) => ! $get('eleve_existant'))->required(fn ($get) => ! $get('eleve_existant')),
                    FileUpload::make('eleve.photo')
                        ->label('Prendre une photo ou choisir un fichier')
                        ->helperText('Sur mobile : choisissez Appareil photo ou Galerie/Fichiers. Formats JPG, JPEG ou PNG — 5 Mo maximum.')
                        ->disk('public')
                        ->directory('eleves/photos')
                        ->image()
                        ->imageEditor()
                        ->acceptedFileTypes(ElevePhoto::MIME_TYPES)
                        ->maxSize(ElevePhoto::MAX_SIZE_KO)
                        ->rules(ElevePhoto::validationRules())
                        ->imagePreviewHeight('180')
                        ->previewable()
                        ->visible(fn ($get) => ! $get('eleve_existant')),
                ]),
                Step::make('Parent ou tuteur')->description('Responsable légal et payeur')->schema([
                    Toggle::make('parent_existant')->label('Le parent possède déjà un dossier')->live()->default(false),
                    Select::make('parent_id')->label('Parent ou tuteur')->options(function ($get) {
                        $query = ParentTuteur::query()->whereNotNull('email')->orderBy('nom');

                        if (filled($get('eleve_id'))) {
                            $query->whereHas('eleves', fn (Builder $query) => $query->whereKey($get('eleve_id')));
                        }

                        return $query->get()->mapWithKeys(fn (ParentTuteur $p) => [$p->id => "{$p->nom} {$p->prenom} — {$p->email}"]);
                    })->searchable()->visible(fn ($get) => (bool) $get('parent_existant'))->required(fn ($get) => (bool) $get('parent_existant')),
                    TextInput::make('parent.nom')->label('Nom')->maxLength(100)->visible(fn ($get) => ! $get('parent_existant'))->required(fn ($get) => ! $get('parent_existant')),
                    TextInput::make('parent.prenom')->label('Prénom')->maxLength(100)->visible(fn ($get) => ! $get('parent_existant'))->required(fn ($get) => ! $get('parent_existant')),
                    TextInput::make('parent.telephone')->label('Téléphone')->tel()->maxLength(30)->visible(fn ($get) => ! $get('parent_existant'))->required(fn ($get) => ! $get('parent_existant')),
                    TextInput::make('parent.email')->label('E-mail de facturation')->email()->maxLength(150)->visible(fn ($get) => ! $get('parent_existant'))->required(fn ($get) => ! $get('parent_existant')),
                    TextInput::make('parent.profession')->label('Profession')->maxLength(150)->visible(fn ($get) => ! $get('parent_existant')),
                    Select::make('lien_parente')->label('Lien avec l’élève')->options(['pere' => 'Père', 'mere' => 'Mère', 'tuteur' => 'Tuteur ou tutrice', 'autre' => 'Autre'])->required(),
                ]),
                Step::make('Classe')->description('Année active et classe demandée')->schema([
                    Placeholder::make('annee_active')->label('Année scolaire active')->content(function () {
                        try {
                            return app(AnneeScolaireServiceContract::class)->getAnneeCourante()->libelle;
                        } catch (\Throwable $e) {
                            return 'Aucune année scolaire active (Activez une année dans le module Socle)';
                        }
                    }),
                    Hidden::make('annee_scolaire_id')->default(function () {
                        try {
                            return app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                        } catch (\Throwable $e) {
                            return null;
                        }
                    })->required(),
                    Select::make('classe_id')->label('Classe demandée')->options(function () {
                        try {
                            $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();

                            return Classe::query()->where('annee_scolaire_id', $anneeId)->pluck('nom', 'id');
                        } catch (\Throwable $e) {
                            return [];
                        }
                    })->searchable()->live()->required(),
                ]),
                Step::make('Versement')->description('Frais calculés et montant disponible')->schema([
                    Placeholder::make('frais_obligatoires')->label("Frais d'inscription obligatoires")
                        ->content(function ($get): string {
                            $frais = self::fraisDisponibles($get('classe_id'), $get('annee_scolaire_id'));
                            $obligatoire = $frais['obligatoire'] ?? null;

                            return $obligatoire ? $obligatoire['label'].' — '.number_format($obligatoire['montant'], 0, ',', ' ').' FCFA' : 'Non configurés';
                        }),
                    CheckboxList::make('frais_optionnels')->label('Frais divers facultatifs proposés au parent')
                        ->options(function ($get): array {
                            $frais = self::fraisDisponibles($get('classe_id'), $get('annee_scolaire_id'));

                            return collect($frais['optionnels'] ?? [])->mapWithKeys(fn (array $item, string $code): array => [
                                $code => $item['label'].' — '.number_format($item['montant'], 0, ',', ' ').' FCFA',
                            ])->all();
                        })->helperText('Le parent choisit librement les frais divers à ajouter. Laissez toutes les cases décochées s’il n’en souhaite aucun.')->live(),
                    Placeholder::make('total_facture')->label('Total de la facture provisoire')
                        ->content(function ($get): string {
                            $frais = self::fraisDisponibles($get('classe_id'), $get('annee_scolaire_id'));
                            $total = (float) ($frais['obligatoire']['montant'] ?? 0)
                                + (float) ($frais['total_scolarite'] ?? 0);
                            foreach ((array) $get('frais_optionnels') as $code) {
                                $total += (float) ($frais['optionnels'][$code]['montant'] ?? 0);
                            }

                            return number_format($total, 0, ',', ' ').' FCFA';
                        }),
                    TextInput::make('montant_verse')->label('Montant que le parent peut verser maintenant')
                        ->numeric()->minValue(1)->suffix('FCFA')->required()
                        ->helperText('Le reste dû sera conservé. La Comptable confirmera uniquement le montant réellement reçu.'),
                ]),
                Step::make('Confirmation')->description('Facture provisoire et contrôle comptable')->schema([
                    Placeholder::make('confirmation')
                        ->hiddenLabel()
                        ->content(fn () => view('scolarite::filament.inscription-confirmation-callout')),
                ]),
            ])->columnSpanFull()]);
    }

    private static function fraisDisponibles(mixed $classeId, mixed $anneeId): array
    {
        if (! filled($classeId) || ! filled($anneeId)) {
            return [];
        }

        $classe = Classe::query()->find($classeId);

        return $classe ? app(InscriptionFacturationPort::class)->getFraisDisponibles((int) $classe->niveau_id, (int) $anneeId, (int) $classe->id) : [];
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $cards = $livewire instanceof Pages\ListInscriptions
            && method_exists($livewire, 'usesCardLayout')
            && $livewire->usesCardLayout();

        return $table
            ->columns($cards ? self::cardColumns() : self::tableColumns())
            ->contentGrid($cards ? [
                'default' => 1,
                'sm' => 2,
                'md' => 2,
                'lg' => 3,
            ] : null)
            ->filters([
                Tables\Filters\SelectFilter::make('statut')->options([
                    'en_cours' => 'En cours',
                    'en_attente_versement' => 'En attente de versement',
                    'validee' => 'Validée',
                    'annulee' => 'Annulée',
                ]),
                Tables\Filters\SelectFilter::make('classe_id')
                    ->label('Classe')
                    ->options(fn () => Classe::query()->orderBy('nom')->pluck('nom', 'id')),
                Tables\Filters\SelectFilter::make('sexe')
                    ->label('Sexe')
                    ->options(['M' => 'Masculin', 'F' => 'Féminin'])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        filled($data['value'] ?? null),
                        fn ($query) => $query->whereHas('eleve', fn ($q) => $q->where('sexe', $data['value'])),
                    )),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->contentFooter(function ($livewire) {
                $query = $livewire->getFilteredTableQuery();

                $total = (clone $query)->count();

                $filles = (clone $query)
                    ->whereHas('eleve', fn ($q) => $q->where('sexe', 'F'))
                    ->count();

                $garcons = (clone $query)
                    ->whereHas('eleve', fn ($q) => $q->where('sexe', 'M'))
                    ->count();

                return view('scolarite::filament.tables.eleves-statistics', [
                    'titre' => 'Total inscriptions',
                    'total' => $total,
                    'filles' => $filles,
                    'garcons' => $garcons,
                ]);
            });
    }

    /** @return array<int, mixed> */
    private static function tableColumns(): array
    {
        return [
            Tables\Columns\ImageColumn::make('eleve.photo')
                ->label('Photo')
                ->disk('public')
                ->defaultImageUrl(asset('images/student-placeholder.svg'))
                ->circular()
                ->size(44),
            Tables\Columns\TextColumn::make('eleve.nom')
                ->label('Élève')
                ->formatStateUsing(fn (Inscription $record): string => trim("{$record->eleve->prenom} {$record->eleve->nom}"))
                ->searchable(['nom', 'prenom'])
                ->sortable(),
            Tables\Columns\TextColumn::make('classe.nom')->label('Classe')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('annee_scolaire_id')->label('Année scolaire (ID)'),
            Tables\Columns\TextColumn::make('type')->label('Type')->badge(),
            Tables\Columns\TextColumn::make('date_inscription')->label('Date')->date('d/m/Y')->sortable(),
            self::statusColumn(),
        ];
    }

    /** @return array<int, mixed> */
    private static function cardColumns(): array
    {
        return [
            Stack::make([
                Tables\Columns\ViewColumn::make('carte_inscription')
                    ->view('scolarite::filament.tables.inscription-card'),
            ]),
        ];
    }

    private static function statusColumn(): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('statut')
            ->label('Statut')
            ->badge()
            ->formatStateUsing(fn (string $state): string => match ($state) {
                'en_cours' => 'En cours',
                'en_attente_versement' => 'En attente de versement',
                'validee' => 'Validée',
                'annulee' => 'Annulée',
                default => $state,
            })
            ->color(fn (string $state): string => match ($state) {
                'en_cours' => 'warning',
                'en_attente_versement' => 'warning',
                'validee' => 'success',
                'annulee' => 'danger',
                default => 'gray',
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInscriptions::route('/'),
            'create' => Pages\CreateInscription::route('/create'),
            // La route statique "/print" doit être déclarée AVANT "/{record}",
            // sinon Laravel essaie de résoudre "print" comme un ID d'inscription
            // (ViewInscription) et renvoie une 404.

            'view' => Pages\ViewInscription::route('/{record}'),
        ];
    }
}
