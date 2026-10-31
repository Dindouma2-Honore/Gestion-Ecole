<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Models\User;
use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Filament\Resources\EleveResource\Pages;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Scolarite\Models\TypeDocumentEleve;
use App\Modules\Scolarite\Support\ElevePhoto;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class EleveResource extends Resource
{
    protected static ?string $model = Eleve::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Élèves';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'admission';

    protected static ?string $pluralModelLabel = 'admissions';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité de l’élève')->columns(2)->schema([
                    TextInput::make('nom')->label('Nom')->required()->maxLength(100),
                    TextInput::make('prenom')->label('Prénom')->required()->maxLength(100),
                    DatePicker::make('date_naissance')->label('Date de naissance'),
                    TextInput::make('lieu_naissance')->label('Lieu de naissance')->maxLength(255),
                    Select::make('sexe')
                        ->label('Sexe')
                        ->options(['M' => 'Masculin', 'F' => 'Féminin'])
                        ->required()
                        ->placeholder('Sélectionnez le sexe (M ou F)'),
                    TextInput::make('nationalite')->label('Nationalité')->maxLength(255),
                    TextInput::make('email')->label('E-mail de l’élève')->email()->maxLength(255),
                    FileUpload::make('photo')
                        ->label('Prendre une photo ou choisir un fichier')
                        ->helperText('Formats JPG, JPEG ou PNG — 5 Mo maximum.')
                        ->disk('public')
                        ->image()
                        ->acceptedFileTypes(ElevePhoto::MIME_TYPES)
                        ->maxSize(ElevePhoto::MAX_SIZE_KO)
                        ->rules(ElevePhoto::validationRules())
                        ->directory('eleves/photos')
                        ->imageEditor()
                        ->imagePreviewHeight('180')
                        ->previewable(),
                ]),
                Section::make('Famille et contacts')->columns(2)->schema([
                    TextInput::make('nom_tuteur')->label('Nom du tuteur / parent responsable')->maxLength(255),
                    TextInput::make('telephone_tuteur')->label('Téléphone du tuteur/parent (Contact 1)')->tel()->maxLength(30),
                    TextInput::make('telephone_secondaire')->label('Téléphone du tuteur/parent (Contact 2)')->tel()->maxLength(30),
                    TextInput::make('contact_urgence_nom')->label('Contact principal d’urgence (Nom et contact)')->placeholder('Ex: Oncle Paul - 6XXXXXXXX')->maxLength(150),
                    Toggle::make('info_sms')
                        ->label('Autoriser les informations par SMS (parents sans smartphone Android)')
                        ->helperText('À activer si le parent ne dispose pas d’un smartphone Android ou de WhatsApp pour recevoir les alertes scolaires par SMS standard.')
                        ->default(false),
                ]),
                Section::make('Parcours et situation administrative')->columns(2)->schema([
                    TextInput::make('ancien_etablissement')->label('Ancien établissement')->maxLength(255),
                    TextInput::make('derniere_classe_frequente')
                        ->label('Dernière classe fréquentée')
                        ->placeholder('Ex: 6e, CM2, CP, Form 1, etc.')
                        ->datalist([
                            'Pré-maternelle', 'Maternelle', 'SIL', 'CP', 'CE1', 'CE2', 'CM1', 'CM2',
                            '6e', '5e', '4e', '3e', 'Seconde', 'Première', 'Terminale',
                            'Nursery 1', 'Nursery 2', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6',
                            'Form 1', 'Form 2', 'Form 3', 'Form 4', 'Form 5', 'Lower Sixth', 'Upper Sixth',
                        ])
                        ->maxLength(100)
                        ->live(),
                    TextInput::make('numero_decision')->label('Décision')->maxLength(255),
                    TextInput::make('moyenne_legacy')->label('Dernière moyenne connue')->numeric()->minValue(0),
                    Toggle::make('redoublant')->label('Redoublant')->default(false)->live(),
                    Toggle::make('transfere')->label('Élève transféré')->default(false),
                    Toggle::make('exonere_frais_examen')->label('Exonéré des frais d’examen')->default(false),
                    Toggle::make('exclu_inscription')->label('Interdit de réinscription')->default(false),
                    Toggle::make('demissionne')->label('Démission enregistrée')->default(false),
                    Toggle::make('exclu_scolaire')->label('Exclusion scolaire')->default(false),
                    Textarea::make('observation')->label('Observations')->columnSpanFull(),
                    Select::make('statut')
                        ->label('Statut')
                        ->options([
                            'prospect' => 'Prospect', 'candidat' => 'Candidat', 'admis' => 'Admis',
                            'inscrit' => 'Inscrit', 'actif' => 'Actif', 'suspendu' => 'Suspendu',
                            'retire' => 'Retiré', 'diplome' => 'Diplômé', 'archive' => 'Archivé',
                        ])
                        ->default('admis')
                        ->helperText('Le statut évolue automatiquement lors de l’inscription.'),
                ]),
                Section::make('Inscription & Paiement immédiats')
                    ->description('Permet d’inscrire immédiatement l’élève dans sa classe et de valider son premier versement dès son admission.')
                    ->visible(fn ($livewire): bool => $livewire instanceof Pages\CreateEleve)
                    ->schema([
                        Toggle::make('inscrire_immediatement')
                            ->label('Poursuivre immédiatement l’inscription et le règlement de cet élève')
                            ->helperText('Activez cette option pour choisir la classe demandée et enregistrer la quittance de versement dès la création.')
                            ->default(false)
                            ->live(),
                        Select::make('classe_id')
                            ->label('Classe demandée')
                            ->options(function ($get): array {
                                try {
                                    $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                                    $derniereClasse = $get('derniere_classe_frequente');
                                    $redoublant = (bool) $get('redoublant');

                                    return InscriptionResource::classesAutoriseesPourDerniereClasse($derniereClasse, $anneeId, $redoublant);
                                } catch (\Throwable) {
                                    return [];
                                }
                            })
                            ->default(function ($get): ?int {
                                try {
                                    $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                                    $derniereClasse = $get('derniere_classe_frequente');
                                    $redoublant = (bool) $get('redoublant');

                                    return InscriptionResource::classeRecommandeePourDerniereClasse($derniereClasse, $anneeId, $redoublant);
                                } catch (\Throwable) {
                                    return null;
                                }
                            })
                            ->visible(fn ($get): bool => (bool) $get('inscrire_immediatement'))
                            ->required(fn ($get): bool => (bool) $get('inscrire_immediatement'))
                            ->live(),
                        CheckboxList::make('frais_optionnels')
                            ->label('Frais divers facultatifs')
                            ->options(fn (): array => InscriptionResource::optionsFraisDivers())
                            ->columns(2)
                            ->helperText('Cochez les prestations complémentaires à facturer.')
                            ->visible(fn ($get): bool => (bool) $get('inscrire_immediatement'))
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $minimum = app(InscriptionFacturationPort::class)->calculerMontantMinimum((array) ($state ?? []));
                                $current = (float) ($get('montant_verse') ?? 0);
                                if ($current < $minimum) {
                                    $set('montant_verse', $minimum);
                                }
                            }),
                        TextInput::make('montant_verse')
                            ->label('Montant versé')
                            ->numeric()
                            ->live(debounce: 300)
                            ->default(fn ($get): float => app(InscriptionFacturationPort::class)->calculerMontantMinimum((array) ($get('frais_optionnels') ?? [])))
                            ->minValue(fn ($get): float => app(InscriptionFacturationPort::class)->calculerMontantMinimum((array) ($get('frais_optionnels') ?? [])))
                            ->suffix('FCFA')
                            ->visible(fn ($get): bool => (bool) $get('inscrire_immediatement'))
                            ->required(fn ($get): bool => (bool) $get('inscrire_immediatement'))
                            ->rules([
                                fn ($get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    try {
                                        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                                    } catch (\Throwable) {
                                        $anneeId = null;
                                    }
                                    $simulation = app(InscriptionFacturationPort::class)->simulerRepartition(
                                        montantVerse: (float) $value,
                                        fraisOptionnels: (array) ($get('frais_optionnels') ?? []),
                                        classeId: $get('classe_id') ? (int) $get('classe_id') : null,
                                        anneeId: $anneeId,
                                    );
                                    if (! $simulation['est_suffisant']) {
                                        $minFormate = number_format($simulation['montant_minimum'], 0, ',', ' ');
                                        if ($simulation['has_divers']) {
                                            $fail("Transaction impossible. Le montant minimum à payer est de {$minFormate} FCFA, comprenant l'inscription et les frais divers sélectionnés.");
                                        } else {
                                            $fail("Transaction impossible. Le montant minimum à payer est de {$minFormate} FCFA, correspondant aux frais d'inscription.");
                                        }
                                    } elseif (! empty($simulation['depassement_total'])) {
                                        $verseFormate = number_format((float) $value, 0, ',', ' ');
                                        $totalFormate = number_format($simulation['total_annuel'], 0, ',', ' ');
                                        $tropPercu = number_format($simulation['trop_percu'], 0, ',', ' ');
                                        $fail("Transaction impossible. Le montant versé de {$verseFormate} FCFA dépasse le montant total annuel dû ({$totalFormate} FCFA). Monnaie à restituer au parent : {$tropPercu} FCFA.");
                                    }
                                },
                            ]),
                        Select::make('mode_paiement')
                            ->label('Mode de règlement')
                            ->options([
                                'especes' => 'Espèces',
                                'mobile_money' => 'Mobile Money',
                                'bancaire' => 'Virement / Versement bancaire',
                            ])
                            ->default('especes')
                            ->visible(fn ($get): bool => (bool) $get('inscrire_immediatement'))
                            ->required(fn ($get): bool => (bool) $get('inscrire_immediatement'))
                            ->live(),
                        TextInput::make('reference_transaction')
                            ->label('Référence transaction (Mobile Money / Banque)')
                            ->visible(fn ($get): bool => (bool) $get('inscrire_immediatement') && in_array($get('mode_paiement'), ['mobile_money', 'bancaire']))
                            ->required(fn ($get): bool => (bool) $get('inscrire_immediatement') && $get('mode_paiement') === 'mobile_money'),
                        Placeholder::make('recapitulatif_paiement')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(fn ($get): bool => (bool) $get('inscrire_immediatement'))
                            ->content(function ($get) {
                                try {
                                    $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                                } catch (\Throwable) {
                                    $anneeId = null;
                                }
                                $simulation = app(InscriptionFacturationPort::class)->simulerRepartition(
                                    montantVerse: (float) ($get('montant_verse') ?? 0),
                                    fraisOptionnels: (array) ($get('frais_optionnels') ?? []),
                                    classeId: $get('classe_id') ? (int) $get('classe_id') : null,
                                    anneeId: $anneeId,
                                    eleveId: null,
                                );

                                return view('scolarite::filament.components.recapitulatif-paiement', ['simulation' => $simulation]);
                            }),
                        Toggle::make('valider_paiement')
                            ->label('Valider immédiatement le paiement et l’inscription')
                            ->default(true)
                            ->visible(fn ($get): bool => (bool) $get('inscrire_immediatement'))
                            ->helperText('Enregistre le paiement et valide l’inscription sans étape supplémentaire.'),
                    ]),
                Section::make('Documents d’admission')->schema([
                    Repeater::make('documentsAdmission')->relationship()->label('Pièces du dossier')->defaultItems(0)->schema([
                        Select::make('type_document_eleve_id')
                            ->label('Type de pièce')
                            ->options(fn (): array => TypeDocumentEleve::query()->where('actif', true)->orderByDesc('obligatoire')->orderBy('nom')->get()->mapWithKeys(fn ($type): array => [$type->id => $type->nom.($type->obligatoire ? ' — obligatoire' : '')])->all())
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('nom')
                                    ->label('Nom de la pièce')
                                    ->required()
                                    ->maxLength(150),
                                Toggle::make('obligatoire')
                                    ->label('Pièce obligatoire')
                                    ->default(false),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $nom = trim($data['nom']);
                                $code = Str::slug($nom, '_');
                                $code = strtoupper(substr($code, 0, 40));

                                $baseCode = $code ?: 'DOC';
                                $uniqueCode = $baseCode;
                                $counter = 1;
                                while (TypeDocumentEleve::where('code', $uniqueCode)->exists()) {
                                    $uniqueCode = substr($baseCode, 0, 35).'_'.$counter;
                                    $counter++;
                                }

                                $type = TypeDocumentEleve::create([
                                    'code' => $uniqueCode,
                                    'nom' => $nom,
                                    'obligatoire' => (bool) ($data['obligatoire'] ?? false),
                                    'actif' => true,
                                ]);

                                return $type->id;
                            })
                            ->required()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        FileUpload::make('fichier')
                            ->label('Document numérisé')
                            ->disk('public')
                            ->directory('eleves/documents')
                            ->required(),
                        DatePicker::make('date_ajout')
                            ->label('Date d’ajout')
                            ->default(today())
                            ->required(),
                    ])->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                        $data['ajoute_par'] = auth()->id() ?? User::first()?->id ?? 1;

                        return $data;
                    })->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();

        $cards = $livewire instanceof Pages\ListEleves
            && method_exists($livewire, 'usesCardLayout')
            && $livewire->usesCardLayout();

        ScolariteActions::configureTable($table, 'edit');

        return $table
            ->columns($cards ? self::cardColumns() : self::tableColumns())
            ->defaultSort('created_at', 'desc')

            ->contentGrid($cards ? [
                'default' => 1,
                'sm' => 2,
                'md' => 2,
                'lg' => 3,
            ] : null)

            ->filters([
                Tables\Filters\SelectFilter::make('sexe')
                    ->label('Sexe')
                    ->options(['M' => 'Masculin', 'F' => 'Féminin']),
            ])
            ->actions([
                self::inscrireTableAction(),
                ScolariteActions::tableActionGroup([
                    ScolariteActions::view(),
                    ScolariteActions::edit(),
                    ScolariteActions::delete(),
                ]),
            ])
            ->contentFooter(function ($livewire) {
                $query = $livewire->getFilteredTableQuery();

                $total = (clone $query)->count();

                $filles = (clone $query)
                    ->whereIn('sexe', ['F', 'f'])
                    ->count();

                $garcons = (clone $query)
                    ->where(function ($q) {
                        $q->whereIn('sexe', ['M', 'm'])
                            ->orWhereNull('sexe');
                    })
                    ->count();

                return view('scolarite::filament.tables.eleves-statistics', [
                    'titre' => 'Total admis',
                    'total' => $total,
                    'filles' => $filles,
                    'garcons' => $garcons,
                ]);
            });
    }

    public static function inscrireTableAction(): Action
    {
        return AmbassadorsDesign::goldenFormModal(
            Action::make('inscrire')
                ->label('Inscrire l’élève')
                ->icon('heroicon-o-academic-cap')
                ->color('success')
                ->tooltip('Inscrire cet élève et enregistrer son paiement dans une modale')
                ->visible(fn (Eleve $record): bool => ! self::estInscritAnneeActive($record))
                ->modalHeading(fn (Eleve $record): string => "Inscription — {$record->nom} {$record->prenom}")
                ->modalDescription(function (Eleve $record): string {
                    try {
                        $annee = app(AnneeScolaireServiceContract::class)->getAnneeCourante()->libelle;
                    } catch (\Throwable) {
                        $annee = 'Année active';
                    }

                    return "Inscription au compte de l’année scolaire {$annee}. Sélectionnez la classe et validez le versement.";
                })
                ->modalSubmitActionLabel('Valider l’inscription')
                ->form([
                    Grid::make(3)->schema([
                        Placeholder::make('annee_scolaire_info')
                            ->label('Année scolaire active')
                            ->content(function () {
                                try {
                                    return app(AnneeScolaireServiceContract::class)->getAnneeCourante()->libelle;
                                } catch (\Throwable) {
                                    return 'Non définie';
                                }
                            }),
                        Placeholder::make('derniere_classe_info')
                            ->label('Dernière classe fréquentée')
                            ->content(function (Eleve $record): string {
                                $classe = $record->derniere_classe_frequente ?: 'Non renseignée';
                                $statut = $record->redoublant ? 'Redoublant' : 'Passage en classe supérieure';

                                return "{$classe} ({$statut})";
                            }),
                        Placeholder::make('parent_tuteur_info')
                            ->label('Parent / Tuteur associé')
                            ->content(fn (Eleve $record): string => trim((string) $record->nom_tuteur).($record->telephone_tuteur ? " — {$record->telephone_tuteur}" : '')),
                    ]),
                    Select::make('classe_id')
                        ->label('Classe demandée')
                        ->options(function (Eleve $record): array {
                            try {
                                $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();

                                return InscriptionResource::classesAutoriseesPourEleve($record->id, $anneeId);
                            } catch (\Throwable) {
                                return [];
                            }
                        })
                        ->default(function (Eleve $record): ?int {
                            try {
                                $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();

                                return InscriptionResource::classeRecommandeePourEleve($record->id, $anneeId);
                            } catch (\Throwable) {
                                return null;
                            }
                        })
                        ->searchable()
                        ->required()
                        ->live(),
                    CheckboxList::make('frais_optionnels')
                        ->label('Frais divers facultatifs')
                        ->options(fn (): array => InscriptionResource::optionsFraisDivers())
                        ->columns(2)
                        ->helperText('Cochez les prestations complémentaires à facturer.')
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $minimum = app(InscriptionFacturationPort::class)->calculerMontantMinimum((array) ($state ?? []));
                            $current = (float) ($get('montant_verse') ?? 0);
                            if ($current < $minimum) {
                                $set('montant_verse', $minimum);
                            }
                        }),
                    Grid::make(2)->schema([
                        TextInput::make('montant_verse')
                            ->label('Montant versé')
                            ->numeric()
                            ->live(debounce: 300)
                            ->default(fn ($get): float => app(InscriptionFacturationPort::class)->calculerMontantMinimum((array) ($get('frais_optionnels') ?? [])))
                            ->minValue(fn ($get): float => app(InscriptionFacturationPort::class)->calculerMontantMinimum((array) ($get('frais_optionnels') ?? [])))
                            ->suffix('FCFA')
                            ->required()
                            ->rules([
                                fn ($get, ?Eleve $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    try {
                                        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                                    } catch (\Throwable) {
                                        $anneeId = null;
                                    }
                                    $simulation = app(InscriptionFacturationPort::class)->simulerRepartition(
                                        montantVerse: (float) $value,
                                        fraisOptionnels: (array) ($get('frais_optionnels') ?? []),
                                        classeId: $get('classe_id') ? (int) $get('classe_id') : null,
                                        anneeId: $anneeId,
                                        eleveId: $record?->id,
                                    );
                                    if (! $simulation['est_suffisant']) {
                                        $minFormate = number_format($simulation['montant_minimum'], 0, ',', ' ');
                                        if ($simulation['has_divers']) {
                                            $fail("Transaction impossible. Le montant minimum à payer est de {$minFormate} FCFA, comprenant l'inscription et les frais divers sélectionnés.");
                                        } else {
                                            $fail("Transaction impossible. Le montant minimum à payer est de {$minFormate} FCFA, correspondant aux frais d'inscription.");
                                        }
                                    } elseif (! empty($simulation['depassement_total'])) {
                                        $verseFormate = number_format((float) $value, 0, ',', ' ');
                                        $totalFormate = number_format($simulation['total_annuel'], 0, ',', ' ');
                                        $tropPercu = number_format($simulation['trop_percu'], 0, ',', ' ');
                                        $fail("Transaction impossible. Le montant versé de {$verseFormate} FCFA dépasse le montant total annuel dû ({$totalFormate} FCFA). Monnaie à restituer au parent : {$tropPercu} FCFA.");
                                    }
                                },
                            ]),
                        Select::make('mode_paiement')
                            ->label('Mode de règlement')
                            ->options([
                                'especes' => 'Espèces',
                                'mobile_money' => 'Mobile Money',
                                'bancaire' => 'Virement / Versement bancaire',
                            ])
                            ->default('especes')
                            ->required()
                            ->live(),
                    ]),
                    TextInput::make('reference_transaction')
                        ->label('Référence transaction (Mobile Money / Banque)')
                        ->visible(fn ($get): bool => in_array($get('mode_paiement'), ['mobile_money', 'bancaire']))
                        ->required(fn ($get): bool => $get('mode_paiement') === 'mobile_money'),
                    Placeholder::make('recapitulatif_paiement')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(function ($get, Eleve $record) {
                            try {
                                $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                            } catch (\Throwable) {
                                $anneeId = null;
                            }
                            $simulation = app(InscriptionFacturationPort::class)->simulerRepartition(
                                montantVerse: (float) ($get('montant_verse') ?? 0),
                                fraisOptionnels: (array) ($get('frais_optionnels') ?? []),
                                classeId: $get('classe_id') ? (int) $get('classe_id') : null,
                                anneeId: $anneeId,
                                eleveId: $record->id,
                            );

                            return view('scolarite::filament.components.recapitulatif-paiement', ['simulation' => $simulation]);
                        }),
                    Toggle::make('valider_paiement')
                        ->label('Valider immédiatement le paiement et l’inscription')
                        ->default(true)
                        ->helperText('Enregistre la quittance, valide l’inscription et transfère l’élève dans les Inscriptions.'),
                ])
                ->action(function (Eleve $record, array $data): void {
                    try {
                        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                        $parent = $record->synchroniserParentTuteur();
                        if (! $parent) {
                            $parent = $record->parentsTuteurs()->firstOrCreate(
                                ['nom' => trim((string) $record->nom_tuteur) ?: "Parent de {$record->nom} {$record->prenom}"],
                                [
                                    'prenom' => '',
                                    'telephone' => $record->telephone_tuteur,
                                    'email' => $record->email,
                                    'portail_actif' => true,
                                ]
                            );
                            $record->parentsTuteurs()->syncWithoutDetaching([
                                $parent->id => [
                                    'lien' => 'tuteur',
                                    'responsable_legal' => true,
                                    'responsable_paiement' => true,
                                    'autorise_recuperation' => true,
                                ],
                            ]);
                        }

                        $inscriptionId = app(InscriptionServiceInterface::class)->demarrerPreinscription(
                            eleveId: $record->id,
                            classeId: (int) $data['classe_id'],
                            anneeScolaireId: $anneeId,
                            parentId: $parent->id,
                            fraisOptionnels: array_values($data['frais_optionnels'] ?? []),
                            montantVerse: (float) $data['montant_verse'],
                        );

                        if (! empty($data['valider_paiement'])) {
                            $facture = FacturePreinscription::where('inscription_id', $inscriptionId)->first();
                            if ($facture) {
                                app(FacturePreinscriptionServiceContract::class)->confirmerVersement(
                                    factureId: $facture->id,
                                    mode: $data['mode_paiement'],
                                    referenceTransaction: $data['reference_transaction'] ?? null,
                                    montant: (float) $data['montant_verse'],
                                );
                            }

                            Notification::make()
                                ->title('Inscription et paiement validés avec succès !')
                                ->body("L’élève {$record->nom} {$record->prenom} est désormais inscrit en classe. Vous pouvez consulter ou imprimer le reçu et la facture ci-dessous.")
                                ->success()
                                ->actions([
                                    Action::make('voirRecu')
                                        ->label('Imprimer le reçu')
                                        ->icon('heroicon-o-receipt-percent')
                                        ->color('success')
                                        ->url(route('impressions.inscriptions.recu', ['inscription' => $inscriptionId]), shouldOpenInNewTab: true),
                                    Action::make('voirFacture')
                                        ->label('Facture provisoire')
                                        ->icon('heroicon-o-document-text')
                                        ->color('primary')
                                        ->url(route('impressions.inscriptions.facture', ['inscription' => $inscriptionId]), shouldOpenInNewTab: true),
                                ])
                                ->persistent()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Préinscription enregistrée')
                                ->body("L’élève {$record->nom} {$record->prenom} a été enregistré en attente de versement comptable.")
                                ->info()
                                ->actions([
                                    Action::make('voirFacture')
                                        ->label('Facture provisoire')
                                        ->icon('heroicon-o-document-text')
                                        ->color('primary')
                                        ->url(route('impressions.inscriptions.facture', ['inscription' => $inscriptionId]), shouldOpenInNewTab: true),
                                ])
                                ->persistent()
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title('Erreur lors de l’inscription')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                })
        );
    }

    /** @return array<int, mixed> */
    private static function tableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('matricule_permanent')
                ->label('Matricule')
                ->placeholder('—')
                ->searchable(),

            Tables\Columns\TextColumn::make('nom')
                ->label('Nom')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('prenom')
                ->label('Prénom')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('sexe')
                ->label('Sexe')
                ->badge()
                ->color(fn (?string $state): string => match ($state) {
                    'F', 'f' => 'pink',
                    'M', 'm' => 'info',
                    default => 'gray',
                }),

            Tables\Columns\TextColumn::make('derniere_classe_frequente')
                ->label('Dernière classe')
                ->placeholder('—')
                ->badge()
                ->color('info')
                ->sortable(),

            Tables\Columns\TextColumn::make('statut')
                ->label('Statut')
                ->badge(),

            Tables\Columns\TextColumn::make('documents_manquants')
                ->label('Documents')
                ->state(fn (Eleve $record): string => $record->documents_obligatoires_manquants->isEmpty() ? 'Complet' : $record->documents_obligatoires_manquants->count().' manquant(s)')
                ->badge()
                ->color(fn (string $state): string => $state === 'Complet' ? 'success' : 'warning')
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Créé le')
                ->dateTime('d/m/Y')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /** @return array<int, mixed> */

    // Eleve :
    private static function cardColumns(): array
    {
        return [
            Stack::make([
                Tables\Columns\ViewColumn::make('carte_eleve')
                    ->view('scolarite::filament.tables.carte-eleve'),
            ]),
        ];
    }

    public static function estInscritAnneeActive(Eleve $eleve): bool
    {
        try {
            $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
        } catch (\Throwable) {
            $anneeId = null;
        }

        return $anneeId !== null && $eleve->inscriptions()
            ->withoutGlobalScopes()
            ->where('annee_scolaire_id', $anneeId)
            ->whereNotIn('statut', ['annulee'])
            ->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        $anneeId = null;
        try {
            $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
        } catch (\Throwable) {
            // ignore – no active school year
        }

        $query = parent::getEloquentQuery()
            ->where('statut', 'admis');

        if ($anneeId !== null) {
            $query->whereDoesntHave('inscriptions', function (Builder $q) use ($anneeId) {
                $q->where('annee_scolaire_id', $anneeId)
                    ->whereNotIn('statut', ['annulee']);
            });
        }

        return $query->latest('created_at')->latest('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEleves::route('/'),
            'create' => Pages\CreateEleve::route('/create'),
            'edit' => Pages\EditEleve::route('/{record}/edit'),
        ];
    }
}
