<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Filament\Support\ModuleAccess;
use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages;
use App\Modules\Scolarite\Filament\Support\ScolariteActions;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class InscriptionResource extends Resource
{
    protected static ?string $model = Inscription::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Inscriptions / Réinscriptions';

    protected static ?int $navigationSort = 5;

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
                    Select::make('eleve_id')
                        ->label('Dossier élève')
                        ->default(fn () => request()->query('eleve_id'))
                        ->options(function ($get): array {
                            try {
                                $anneeId = $get('annee_scolaire_id') ? (int) $get('annee_scolaire_id') : app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                            } catch (\Throwable) {
                                $anneeId = null;
                            }
                            $query = Eleve::query();
                            if ($anneeId) {
                                $query->whereDoesntHave('inscriptions', fn ($q) => $q->withoutGlobalScopes()->where('annee_scolaire_id', $anneeId)->whereNotIn('statut', ['annulee']));
                            }

                            return $query->orderBy('nom')->orderBy('prenom')->get()->mapWithKeys(function (Eleve $e): array {
                                $infos = [];
                                if ($e->matricule_permanent) {
                                    $infos[] = $e->matricule_permanent;
                                }
                                if ($e->derniere_classe_frequente) {
                                    $infos[] = "Dernière cl: {$e->derniere_classe_frequente}";
                                }
                                $statutBadge = $e->statut === 'admis' ? ' — 🟡 Admis' : ' — '.ucfirst((string) $e->statut);
                                $suffix = ! empty($infos) ? ' ('.implode(' — ', $infos).')' : '';

                                return [$e->id => trim("{$e->nom} {$e->prenom}").$suffix.$statutBadge];
                            })->all();
                        })
                        ->searchable()
                        ->live()
                        ->rules([
                            fn ($get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                try {
                                    $anneeId = $get('annee_scolaire_id') ? (int) $get('annee_scolaire_id') : app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                                } catch (\Throwable) {
                                    $anneeId = null;
                                }
                                if ($anneeId && $value && app(InscriptionServiceInterface::class)->estInscrit((int) $value, (int) $anneeId)) {
                                    $fail("Cet élève possède déjà une inscription active pour l'année scolaire sélectionnée.");
                                }
                            },
                        ])
                        ->afterStateUpdated(function ($state, callable $set): void {
                            if (! filled($state)) {
                                $set('parent_id', null);

                                return;
                            }

                            $eleve = Eleve::query()->with('parentsTuteurs')->find($state);
                            if ($eleve) {
                                $eleve->synchroniserParentTuteur();
                                $eleve->load('parentsTuteurs');
                            }

                            $parent = $eleve?->parentsTuteurs
                                ->first(fn (ParentTuteur $parent): bool => (bool) $parent->pivot?->responsable_paiement)
                                ?? $eleve?->parentsTuteurs->first();

                            $set('parent_id', $parent?->id);
                            $set('lien_parente', $parent?->pivot?->lien ?: 'tuteur');
                        })
                        ->required(),
                    Hidden::make('parent_id')
                        ->default(function () {
                            $eleveId = request()->query('eleve_id');
                            if (! $eleveId) {
                                return null;
                            }
                            $eleve = Eleve::query()->with('parentsTuteurs')->find($eleveId);
                            if ($eleve) {
                                $eleve->synchroniserParentTuteur();
                                $eleve->load('parentsTuteurs');
                                $parent = $eleve->parentsTuteurs
                                    ->first(fn (ParentTuteur $parent): bool => (bool) $parent->pivot?->responsable_paiement)
                                    ?? $eleve->parentsTuteurs->first();

                                return $parent?->id;
                            }

                            return null;
                        })
                        ->required(),
                    Hidden::make('lien_parente')
                        ->default(function () {
                            $eleveId = request()->query('eleve_id');
                            if (! $eleveId) {
                                return 'tuteur';
                            }
                            $eleve = Eleve::query()->with('parentsTuteurs')->find($eleveId);
                            if ($eleve) {
                                $parent = $eleve->parentsTuteurs
                                    ->first(fn (ParentTuteur $parent): bool => (bool) $parent->pivot?->responsable_paiement)
                                    ?? $eleve->parentsTuteurs->first();

                                return $parent?->pivot?->lien ?: 'tuteur';
                            }

                            return 'tuteur';
                        })
                        ->required(),
                    Placeholder::make('parent_associe')->label('Parent ou tuteur associé')->content(function ($get): string {
                        $parent = filled($get('parent_id')) ? ParentTuteur::query()->find($get('parent_id')) : null;

                        return $parent ? trim("{$parent->nom} {$parent->prenom}").($parent->telephone ? " — {$parent->telephone}" : '') : 'Aucun parent associé — complétez d’abord le dossier de l’élève.';
                    }),
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
                    Select::make('classe_id')->label('Classe demandée')->options(function ($get) {
                        try {
                            $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();

                            return self::classesAutoriseesPourEleve($get('eleve_id'), $anneeId);
                        } catch (\Throwable $e) {
                            return [];
                        }
                    })
                        ->searchable()
                        ->live()
                        ->helperText('Seules la classe du dernier niveau (redoublement) et les classes de niveaux supérieurs sont proposées.')
                        ->required(),
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
                        })
                        ->helperText('Le parent choisit librement les frais divers à ajouter. Laissez toutes les cases décochées s’il n’en souhaite aucun.')
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $minimum = app(InscriptionFacturationPort::class)->calculerMontantMinimum((array) ($state ?? []));
                            $current = (float) ($get('montant_verse') ?? 0);
                            if ($current < $minimum) {
                                $set('montant_verse', $minimum);
                            }
                        }),
                    Placeholder::make('total_facture')->label('Total annuel restant à couvrir')
                        ->content(function ($get): string {
                            $frais = self::fraisDisponibles($get('classe_id'), $get('annee_scolaire_id'));
                            $total = (float) ($frais['obligatoire']['montant'] ?? 0)
                                + (float) ($frais['total_scolarite'] ?? 0);
                            foreach ((array) $get('frais_optionnels') as $code) {
                                $total += (float) ($frais['optionnels'][$code]['montant'] ?? 0);
                            }

                            return number_format($total, 0, ',', ' ').' FCFA';
                        }),
                    TextInput::make('montant_verse')->label('Versement initial')
                        ->numeric()
                        ->live(debounce: 300)
                        ->default(fn ($get): float => app(InscriptionFacturationPort::class)->calculerMontantMinimum((array) ($get('frais_optionnels') ?? [])))
                        ->minValue(fn ($get): float => app(InscriptionFacturationPort::class)->calculerMontantMinimum((array) ($get('frais_optionnels') ?? [])))
                        ->suffix('FCFA')
                        ->required()
                        ->rules([
                            fn ($get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                $simulation = app(InscriptionFacturationPort::class)->simulerRepartition(
                                    montantVerse: (float) $value,
                                    fraisOptionnels: (array) ($get('frais_optionnels') ?? []),
                                    classeId: $get('classe_id') ? (int) $get('classe_id') : null,
                                    anneeId: $get('annee_scolaire_id') ? (int) $get('annee_scolaire_id') : null,
                                    eleveId: $get('eleve_id') ? (int) $get('eleve_id') : null,
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
                    Placeholder::make('recapitulatif_paiement_form')
                        ->hiddenLabel()
                        ->content(function ($get) {
                            $simulation = app(InscriptionFacturationPort::class)->simulerRepartition(
                                montantVerse: (float) ($get('montant_verse') ?? 0),
                                fraisOptionnels: (array) ($get('frais_optionnels') ?? []),
                                classeId: $get('classe_id') ? (int) $get('classe_id') : null,
                                anneeId: $get('annee_scolaire_id') ? (int) $get('annee_scolaire_id') : null,
                                eleveId: $get('eleve_id') ? (int) $get('eleve_id') : null,
                            );

                            return view('scolarite::filament.components.recapitulatif-paiement', ['simulation' => $simulation]);
                        }),
                ]),
                Step::make('Confirmation')->description('Facture provisoire et contrôle comptable')->schema([
                    Placeholder::make('confirmation')
                        ->hiddenLabel()
                        ->content(fn () => view('scolarite::filament.inscription-confirmation-callout')),
                ]),
            ])->columnSpanFull()]);
    }

    public static function optionsFraisDivers(): array
    {
        try {
            $service = app(InscriptionFacturationPort::class);
            $service->assurerFraisDiversParDefaut();

            return \App\Modules\Finances\Models\CatalogueFraisDivers::query()
                ->where('actif', true)
                ->orderBy('nom')
                ->get()
                ->mapWithKeys(fn (\App\Modules\Finances\Models\CatalogueFraisDivers $frais): array => [
                    'divers-'.$frais->id => $frais->nom.' — '.number_format((float) $frais->montant_defaut, 0, ',', ' ').' FCFA',
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function fraisDisponibles(mixed $classeId, mixed $anneeId): array
    {
        if (! filled($classeId) || ! filled($anneeId)) {
            return [];
        }

        $classe = Classe::query()->find($classeId);

        return $classe ? app(InscriptionFacturationPort::class)->getFraisDisponibles((int) $classe->niveau_id, (int) $anneeId, (int) $classe->id) : [];
    }

    public static function determinerOrdreNiveau(?string $classe): ?int
    {
        if (! $classe || trim($classe) === '') {
            return null;
        }

        $str = trim(mb_strtolower($classe));
        $str = str_replace(['è', 'é', 'ê', 'ë'], 'e', $str);
        $str = preg_replace('/\s+/', ' ', $str);

        // 1. Recherche directe dans la table classes
        $classeDb = DB::table('classes')->where('nom', 'LIKE', "%{$classe}%")->first();
        if ($classeDb && $classeDb->niveau_id) {
            $ordre = DB::table('niveaux')->where('id', $classeDb->niveau_id)->value('ordre');
            if ($ordre !== null) {
                return (int) $ordre;
            }
        }

        // 2. Recherche exacte puis partielle dans la table niveaux
        $niveaux = DB::table('niveaux')->get();
        foreach ($niveaux as $n) {
            $nom = trim(mb_strtolower((string) $n->nom));
            $code = trim(mb_strtolower((string) $n->code));
            if ($nom !== '' && ($str === $nom || $str === $code)) {
                return (int) $n->ordre;
            }
        }
        foreach ($niveaux as $n) {
            $nom = trim(mb_strtolower((string) $n->nom));
            $code = trim(mb_strtolower((string) $n->code));
            if ($nom !== '' && (str_contains($str, $nom) || str_contains($nom, $str))) {
                return (int) $n->ordre;
            }
            if ($code !== '' && (str_contains($str, $code) || str_contains($code, $str))) {
                return (int) $n->ordre;
            }
        }

        // 3. Correspondances canoniques avec les niveaux en base
        $canonicalMap = [
            'pre-maternelle' => ['premat', 'pre-mat', 'pre-nur', 'creche', 'daycare'],
            'maternelle' => ['mat', 'nursery', 'petite section', 'moyenne section', 'grande section'],
            'sil' => ['sil', 'class 1'],
            'cp' => ['cp', 'cours prep', 'class 2', 'class 3'],
            'ce1' => ['ce1', 'cours elem 1'],
            'ce2' => ['ce2', 'cours elem 2'],
            'cm1' => ['cm1', 'cours moyen 1', 'class 4', 'class 5'],
            'cm2' => ['cm2', 'cours moyen 2', 'class 6'],
            '6e' => ['6e', '6eme', 'form 1'],
            '5e' => ['5e', '5eme', 'form 2'],
            '4e' => ['4e', '4eme', 'form 3'],
            '3e' => ['3e', '3eme', 'form 4'],
            'seconde' => ['2nde', 'seconde', 'form 5'],
            'premiere' => ['1ere', 'premiere', 'lower sixth'],
            'terminale' => ['tle', 'terminale', 'upper sixth'],
        ];

        foreach ($canonicalMap as $target => $aliases) {
            $matchesKeyword = str_contains($str, $target);
            if (! $matchesKeyword) {
                foreach ($aliases as $alias) {
                    if (str_contains($str, $alias)) {
                        $matchesKeyword = true;
                        break;
                    }
                }
            }

            if ($matchesKeyword) {
                foreach ($niveaux as $n) {
                    $nom = trim(mb_strtolower((string) $n->nom));
                    $code = trim(mb_strtolower((string) $n->code));
                    if (str_contains($nom, $target) || str_contains($code, $target)) {
                        return (int) $n->ordre;
                    }
                }
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public static function classesAutoriseesPourDerniereClasse(?string $derniereClasse, int $anneeId, bool $redoublant = false): array
    {
        $classes = Classe::query()
            ->where('annee_scolaire_id', $anneeId)
            ->where('statut', 'active');

        $ordre = self::determinerOrdreNiveau($derniereClasse);
        if ($ordre !== null) {
            $niveauxAutorises = DB::table('niveaux')
                ->where('ordre', '>=', $ordre)
                ->pluck('id');
            $classes->whereIn('niveau_id', $niveauxAutorises);
        }

        return $classes->orderBy('niveau_id')->orderBy('nom')->pluck('nom', 'id')->all();
    }

    public static function classeRecommandeePourDerniereClasse(?string $derniereClasse, int $anneeId, bool $redoublant = false): ?int
    {
        $ordre = self::determinerOrdreNiveau($derniereClasse);
        if ($ordre === null) {
            return null;
        }

        $ordreCible = $redoublant ? $ordre : ($ordre + 1);

        $classeCible = Classe::query()
            ->join('niveaux', 'classes.niveau_id', '=', 'niveaux.id')
            ->where('classes.annee_scolaire_id', $anneeId)
            ->where('classes.statut', 'active')
            ->where('niveaux.ordre', $ordreCible)
            ->select('classes.id')
            ->first();

        if ($classeCible) {
            return (int) $classeCible->id;
        }

        if (! $redoublant) {
            $classeSuivante = Classe::query()
                ->join('niveaux', 'classes.niveau_id', '=', 'niveaux.id')
                ->where('classes.annee_scolaire_id', $anneeId)
                ->where('classes.statut', 'active')
                ->where('niveaux.ordre', '>=', $ordreCible)
                ->orderBy('niveaux.ordre')
                ->select('classes.id')
                ->first();

            if ($classeSuivante) {
                return (int) $classeSuivante->id;
            }
        }

        $classeMemeNiveau = Classe::query()
            ->join('niveaux', 'classes.niveau_id', '=', 'niveaux.id')
            ->where('classes.annee_scolaire_id', $anneeId)
            ->where('classes.statut', 'active')
            ->where('niveaux.ordre', '>=', $ordre)
            ->orderBy('niveaux.ordre')
            ->select('classes.id')
            ->first();

        return $classeMemeNiveau ? (int) $classeMemeNiveau->id : null;
    }

    /** @return array<int, string> */
    public static function classesAutoriseesPourEleve(mixed $eleveId, int $anneeId): array
    {
        $classes = Classe::query()
            ->where('annee_scolaire_id', $anneeId)
            ->where('statut', 'active');

        if (filled($eleveId)) {
            $derniereInscription = Inscription::query()
                ->withoutGlobalScopes()
                ->with('classe')
                ->where('eleve_id', (int) $eleveId)
                ->whereNotIn('statut', ['annulee'])
                ->latest('date_inscription')
                ->latest('id')
                ->first();
            $ordreDernierNiveau = $derniereInscription?->classe
                ? DB::table('niveaux')->where('id', $derniereInscription->classe->niveau_id)->value('ordre')
                : null;

            if ($ordreDernierNiveau === null) {
                $eleve = Eleve::query()->find($eleveId);
                if ($eleve && filled($eleve->derniere_classe_frequente)) {
                    $ordreDernierNiveau = self::determinerOrdreNiveau($eleve->derniere_classe_frequente);
                }
            }

            if ($ordreDernierNiveau !== null) {
                $niveauxAutorises = DB::table('niveaux')
                    ->where('ordre', '>=', $ordreDernierNiveau)
                    ->pluck('id');
                $classes->whereIn('niveau_id', $niveauxAutorises);
            }
        }

        return $classes->orderBy('niveau_id')->orderBy('nom')->pluck('nom', 'id')->all();
    }

    public static function classeRecommandeePourEleve(mixed $eleveId, int $anneeId): ?int
    {
        if (! filled($eleveId)) {
            return null;
        }

        $eleve = Eleve::query()->find($eleveId);
        if (! $eleve) {
            return null;
        }

        $derniereInscription = Inscription::query()
            ->withoutGlobalScopes()
            ->with('classe')
            ->where('eleve_id', (int) $eleveId)
            ->whereNotIn('statut', ['annulee'])
            ->latest('date_inscription')
            ->latest('id')
            ->first();

        $ordre = $derniereInscription?->classe
            ? (int) DB::table('niveaux')->where('id', $derniereInscription->classe->niveau_id)->value('ordre')
            : self::determinerOrdreNiveau($eleve->derniere_classe_frequente);

        if ($ordre === null) {
            return null;
        }

        return self::classeRecommandeePourDerniereClasse(
            $eleve->derniere_classe_frequente,
            $anneeId,
            (bool) $eleve->redoublant
        );
    }

    public static function nouvelleInscriptionModalAction(): Action
    {
        return AmbassadorsDesign::goldenFormModal(
            Action::make('create')
                ->label('Nouvelle inscription')
                ->icon('heroicon-o-academic-cap')
                ->color('primary')
                ->tooltip('Inscrire un élève et enregistrer son paiement dans une modale')
                ->modalHeading('Nouvelle inscription d’un élève')
                ->modalDescription(function (): string {
                    try {
                        $annee = app(AnneeScolaireServiceContract::class)->getAnneeCourante()->libelle;
                    } catch (\Throwable) {
                        $annee = 'Année active';
                    }

                    return "Inscription pour l’année scolaire {$annee}. Sélectionnez l’élève et sa classe, puis validez le règlement initial.";
                })
                ->modalSubmitActionLabel('Valider l’inscription')
                ->form([
                    Grid::make(2)->schema([
                        Placeholder::make('annee_active_info')
                            ->label('Année scolaire active')
                            ->content(function () {
                                try {
                                    return app(AnneeScolaireServiceContract::class)->getAnneeCourante()->libelle;
                                } catch (\Throwable) {
                                    return 'Non définie';
                                }
                            }),
                        Select::make('eleve_id')
                            ->label('Élève à inscrire')
                            ->options(function (): array {
                                try {
                                    $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();

                                    return Eleve::query()
                                        ->whereDoesntHave('inscriptions', fn ($q) => $q->where('annee_scolaire_id', $anneeId)->whereNotIn('statut', ['annulee']))
                                        ->orderBy('nom')
                                        ->orderBy('prenom')
                                        ->get()
                                        ->mapWithKeys(function (Eleve $e): array {
                                            $infos = [];
                                            if ($e->matricule_permanent) {
                                                $infos[] = $e->matricule_permanent;
                                            }
                                            if ($e->derniere_classe_frequente) {
                                                $infos[] = "Dernière cl: {$e->derniere_classe_frequente}";
                                            }
                                            $suffix = ! empty($infos) ? ' ('.implode(' — ', $infos).')' : '';

                                            return [$e->id => "{$e->nom} {$e->prenom}{$suffix}"];
                                        })
                                        ->all();
                                } catch (\Throwable) {
                                    return [];
                                }
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->rules([
                                fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                    try {
                                        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                                    } catch (\Throwable) {
                                        $anneeId = null;
                                    }
                                    if ($anneeId && $value && app(InscriptionServiceInterface::class)->estInscrit((int) $value, (int) $anneeId)) {
                                        $fail("Cet élève possède déjà une inscription active pour l'année scolaire en cours.");
                                    }
                                },
                            ])
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    return;
                                }
                                try {
                                    $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                                    $recommandee = self::classeRecommandeePourEleve($state, $anneeId);
                                    if ($recommandee) {
                                        $set('classe_id', $recommandee);
                                    }
                                } catch (\Throwable) {}
                            }),
                    ]),
                    Placeholder::make('eleve_details')
                        ->label('Situation de l’élève sélectionné')
                        ->visible(fn ($get): bool => filled($get('eleve_id')))
                        ->content(function ($get): string {
                            $eleveId = $get('eleve_id');
                            if (! $eleveId) {
                                return '';
                            }
                            $eleve = Eleve::query()->find($eleveId);
                            if (! $eleve) {
                                return '';
                            }
                            $situation = $eleve->redoublant ? 'Redoublant' : 'Passage en classe supérieure';
                            $derniereClasse = $eleve->derniere_classe_frequente ?: 'Non renseignée';
                            $parent = trim((string) $eleve->nom_tuteur) . ($eleve->telephone_tuteur ? " ({$eleve->telephone_tuteur})" : '');

                            return "Dernière classe : {$derniereClasse} | Situation : {$situation} | Parent/Tuteur : {$parent}";
                        }),
                    Select::make('classe_id')
                        ->label('Classe demandée')
                        ->options(function ($get): array {
                            try {
                                $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                                $eleveId = $get('eleve_id');

                                return self::classesAutoriseesPourEleve($eleveId, $anneeId);
                            } catch (\Throwable) {
                                return [];
                            }
                        })
                        ->default(function ($get): ?int {
                            try {
                                $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();

                                return self::classeRecommandeePourEleve($get('eleve_id'), $anneeId);
                            } catch (\Throwable) {
                                return null;
                            }
                        })
                        ->searchable()
                        ->required()
                        ->live(),
                    CheckboxList::make('frais_optionnels')
                        ->label('Frais divers facultatifs')
                        ->options(fn (): array => self::optionsFraisDivers())
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
                                        eleveId: $get('eleve_id') ? (int) $get('eleve_id') : null,
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
                    Placeholder::make('recapitulatif_paiement_modal')
                        ->hiddenLabel()
                        ->columnSpanFull()
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
                                eleveId: $get('eleve_id') ? (int) $get('eleve_id') : null,
                            );

                            return view('scolarite::filament.components.recapitulatif-paiement', ['simulation' => $simulation]);
                        }),
                    Toggle::make('valider_paiement')
                        ->label('Valider immédiatement le paiement et l’inscription')
                        ->default(true)
                        ->helperText('Enregistre la quittance, valide l’inscription et transfère l’élève dans les Inscriptions.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                        $eleve = Eleve::findOrFail($data['eleve_id']);
                        $parent = $eleve->synchroniserParentTuteur();
                        if (! $parent) {
                            $parent = $eleve->parentsTuteurs()->firstOrCreate(
                                ['nom' => trim((string) $eleve->nom_tuteur) ?: "Parent de {$eleve->nom} {$eleve->prenom}"],
                                [
                                    'prenom' => '',
                                    'telephone' => $eleve->telephone_tuteur,
                                    'email' => $eleve->email,
                                    'portail_actif' => true,
                                ]
                            );
                            $eleve->parentsTuteurs()->syncWithoutDetaching([
                                $parent->id => [
                                    'lien' => 'tuteur',
                                    'responsable_legal' => true,
                                    'responsable_paiement' => true,
                                    'autorise_recuperation' => true,
                                ],
                            ]);
                        }

                        $inscriptionId = app(InscriptionServiceInterface::class)->demarrerPreinscription(
                            eleveId: $eleve->id,
                            classeId: (int) $data['classe_id'],
                            anneeScolaireId: $anneeId,
                            parentId: $parent->id,
                            fraisOptionnels: array_values($data['frais_optionnels'] ?? []),
                            montantVerse: (float) $data['montant_verse'],
                        );

                        if (! empty($data['valider_paiement'])) {
                            $facture = \App\Modules\Finances\Models\FacturePreinscription::where('inscription_id', $inscriptionId)->first();
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
                                ->body("L’élève {$eleve->nom} {$eleve->prenom} est désormais inscrit en classe. Vous pouvez consulter ou imprimer le reçu et la facture ci-dessous.")
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
                                ->body("L’élève {$eleve->nom} {$eleve->prenom} a été enregistré en attente de versement comptable.")
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
                        if (app()->environment('testing')) {
                            throw $e;
                        }
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            View::make('scolarite::filament.pages.inscription-details-table')
                ->viewData(function (Inscription $record): array {
                    return [
                        'record' => $record->loadMissing([
                            'eleve.parentsTuteurs',
                            'classe',
                            'anneeScolaire',
                            'demandeurAnnulation',
                            'viseurAnnulation',
                            'validateurAnnulation',
                            'transferts.classeOrigine',
                            'transferts.classeDestination',
                            'transferts.auteur',
                        ]),
                        'dossier' => app(InscriptionFacturationPort::class)->getDossierFinancierComplet((int) $record->id),
                        'factureProvisoire' => app(InscriptionFacturationPort::class)->getFactureProvisoire((int) $record->id),
                        'reste' => app(InscriptionFacturationPort::class)->getResteAPayer((int) $record->id),
                        'versements' => app(InscriptionFacturationPort::class)->getVersements((int) $record->id),
                    ];
                })
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $cards = $livewire instanceof Pages\ListInscriptions
            && method_exists($livewire, 'usesCardLayout')
            && $livewire->usesCardLayout();

        ScolariteActions::configureTable($table, 'view');

        return $table
            ->columns($cards ? self::cardColumns() : self::tableColumns())
            ->contentGrid($cards ? [
                'default' => 1,
                'sm' => 2,
                'md' => 2,
                'lg' => 3,
            ] : null)
            ->defaultSort('date_inscription', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('annee_scolaire_id')
                    ->label('Année scolaire')
                    ->options(fn () => \App\Modules\Socle\Models\AnneeScolaire::query()->orderByDesc('date_debut')->pluck('libelle', 'id'))
                    ->default(fn () => app(AnneeScolaireServiceContract::class)->getAnneeCouranteId()),
                Tables\Filters\SelectFilter::make('statut')->options([
                    'en_cours' => 'En cours',
                    'en_attente_versement' => 'En attente de versement',
                    'active' => 'Active',
                    'validee' => 'Validée',
                    'en_attente_annulation' => 'En attente d’annulation',
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
                ActionGroup::make([
                    AmbassadorsDesign::goldenFormModal(ViewAction::make()->label('Consulter la fiche')->icon('heroicon-o-eye')),
                    Action::make('imprimerRecu')
                        ->label('Imprimer le reçu')
                        ->icon('heroicon-o-receipt-percent')
                        ->color('success')
                        ->url(fn (Inscription $record): string => route('impressions.inscriptions.recu', ['inscription' => $record->id]))
                        ->openUrlInNewTab()
                        ->visible(fn (Inscription $record): bool => (bool) ($record->a_paiement_valide ?? false) || ! in_array($record->statut, ['en_attente_versement'], true)),
                    Action::make('imprimerFacture')
                        ->label('Facture provisoire')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->url(fn (Inscription $record): string => route('impressions.inscriptions.facture', ['inscription' => $record->id]))
                        ->openUrlInNewTab(),
                    Action::make('encaisserVersement')
                        ->label('Encaisser')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn (Inscription $record): bool => $record->statut === 'en_attente_versement')
                        ->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('form'))
                        ->form([
                            Select::make('mode')
                                ->label('Mode de paiement')
                                ->options([
                                    'especes' => 'Espèces',
                                    'bancaire' => 'Virement / dépôt bancaire',
                                    'mobile_money' => 'Mobile Money',
                                ])
                                ->required()
                                ->live(),
                            TextInput::make('reference_mobile_money')
                                ->label('Référence Mobile Money')
                                ->visible(fn ($get) => $get('mode') === 'mobile_money')
                                ->required(fn ($get) => $get('mode') === 'mobile_money'),
                        ])
                        ->action(function (Inscription $record, array $data): void {
                            try {
                                $facture = app(InscriptionFacturationPort::class)->getFactureProvisoire((int) $record->id);
                                abort_unless($facture, 422, 'La facture provisoire est introuvable.');

                                app(FacturePreinscriptionServiceContract::class)->confirmerVersement(
                                    $facture->id,
                                    $data['mode'],
                                    $data['reference_mobile_money'] ?? null,
                                );

                                Notification::make()
                                    ->title('Versement enregistré avec succès')
                                    ->actions([
                                        Action::make('voirRecu')
                                            ->label('Imprimer le reçu')
                                            ->icon('heroicon-o-receipt-percent')
                                            ->color('success')
                                            ->url(route('impressions.inscriptions.recu', ['inscription' => $record->id]), shouldOpenInNewTab: true),
                                    ])
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()
                                    ->title('Erreur lors de l’encaissement')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),
                    Action::make('transfererClasse')
                        ->label('Transférer de classe')
                        ->icon('heroicon-o-arrows-right-left')
                        ->color('primary')
                        ->visible(fn (Inscription $record): bool => ! in_array($record->statut, ['annulee'], true))
                        ->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('form'))
                        ->modalHeading('Transfert de classe & Recalcul financier')
                        ->modalDescription('Sélectionnez la nouvelle classe pour prévisualiser immédiatement l’impact financier et la réallocation des tranches avant confirmation.')
                        ->modalSubmitActionLabel('Confirmer le transfert')
                        ->form([
                            Select::make('nouvelle_classe_id')
                                ->label('Nouvelle classe de destination')
                                ->options(fn (Inscription $record): array => Classe::query()
                                    ->where('annee_scolaire_id', $record->annee_scolaire_id)
                                    ->where('statut', 'active')
                                    ->where('id', '!=', $record->classe_id)
                                    ->orderBy('nom')
                                    ->pluck('nom', 'id')
                                    ->all()
                                )
                                ->searchable()
                                ->required()
                                ->live(),

                            Placeholder::make('comparatif_transfert')
                                ->label('Comparatif financier & Tranches')
                                ->visible(fn ($get) => filled($get('nouvelle_classe_id')))
                                ->content(function ($get, Inscription $record) {
                                    $nouvelleClasseId = (int) $get('nouvelle_classe_id');
                                    if (! $nouvelleClasseId) {
                                        return null;
                                    }
                                    $simulation = app(InscriptionFacturationPort::class)->simulerTransfertFinancier((int) $record->id, $nouvelleClasseId);

                                    return view('scolarite::filament.components.transfert-comparatif-table', [
                                        'simulation' => $simulation,
                                    ]);
                                }),

                            Textarea::make('motif')
                                ->label('Motif du transfert')
                                ->placeholder('Ex : Changement de filière, rééquilibrage pédagogique, convenance parentale...')
                                ->required(),

                            DatePicker::make('date_transfert')
                                ->label('Date d’effet du transfert')
                                ->default(now()->toDateString())
                                ->required(),
                        ])
                        ->action(function (Inscription $record, array $data): void {
                            try {
                                app(InscriptionServiceInterface::class)->transfererClasse(
                                    inscriptionId: $record->id,
                                    nouvelleClasseId: (int) $data['nouvelle_classe_id'],
                                    motif: $data['motif'],
                                    dateTransfert: (string) $data['date_transfert'],
                                );

                                Notification::make()
                                    ->title('Transfert de classe effectué avec succès !')
                                    ->body('L’élève a été réaffecté et la scolarité a été automatiquement recalculée.')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()
                                    ->title('Erreur lors du transfert')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Action::make('viserAnnulation')
                        ->label('Viser l’annulation (Comptabilité)')
                        ->icon('heroicon-o-shield-check')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Visa comptable préalable à l’annulation')
                        ->modalDescription('Contrôle à 4 yeux — 1er œil : En tant que Comptable, attestez de la vérification financière (quittances, paiements perçus) avant transmission au Fondateur.')
                        ->modalSubmitActionLabel('Apposer le visa comptable')
                        ->visible(fn (Inscription $record): bool => $record->statut === 'en_attente_annulation' && ! $record->aVisaComptable() && self::estComptable())
                        ->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                        ->form([
                            Textarea::make('notes')
                                ->label('Observations financières / Notes de visa')
                                ->placeholder('Ex : Versements vérifiés, quittances acquittées, aucun avoir bloquant.')
                                ->required(),
                        ])
                        ->action(function (Inscription $record, array $data): void {
                            try {
                                app(InscriptionServiceInterface::class)->viserAnnulation($record->id, $data['notes']);
                                Notification::make()
                                    ->title('Visa comptable apposé avec succès')
                                    ->body('La demande a été visée et transmise au Fondateur pour validation définitive.')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()
                                    ->title('Erreur lors du visa')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Action::make('validerAnnulation')
                        ->label('Valider l’annulation (Fondateur)')
                        ->icon('heroicon-o-check-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Valider l’annulation de l’inscription')
                        ->modalDescription('Contrôle à 4 yeux — 2e œil : Le visa de la comptabilité a été accordé. Cette décision finale libère la place de l’élève et clôture son inscription.')
                        ->modalSubmitActionLabel('Confirmer l’annulation définitive')
                        ->visible(fn (Inscription $record): bool => $record->statut === 'en_attente_annulation' && $record->aVisaComptable() && self::estFondateur())
                        ->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                        ->form([
                            Textarea::make('motif')
                                ->label('Motif / Décision finale du Fondateur')
                                ->default(fn (Inscription $record): ?string => $record->motif_annulation)
                                ->required(),
                        ])
                        ->action(function (Inscription $record, array $data): void {
                            try {
                                app(InscriptionServiceInterface::class)->validerAnnulation($record->id, $data['motif']);
                                Notification::make()
                                    ->title('Annulation validée avec succès par le Fondateur')
                                    ->body('L’inscription a été annulée et l’élève quitte la liste des inscrits actifs.')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()
                                    ->title('Erreur lors de la validation')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Action::make('rejeterAnnulation')
                        ->label('Rejeter la demande d’annulation')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Rejeter la demande d’annulation')
                        ->modalDescription('L’inscription restera active et la place de l’élève sera conservée.')
                        ->visible(fn (Inscription $record): bool => $record->statut === 'en_attente_annulation' && self::estFondateur())
                        ->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                        ->form([
                            Textarea::make('motif')->label('Motif du rejet')->required(),
                        ])
                        ->action(function (Inscription $record, array $data): void {
                            try {
                                app(InscriptionServiceInterface::class)->rejeterAnnulation($record->id, $data['motif']);
                                Notification::make()
                                    ->title('Demande d’annulation rejetée')
                                    ->info()
                                    ->send();
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()
                                    ->title('Erreur lors du rejet')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Action::make('demanderAnnulation')
                        ->label('Demander l’annulation')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Demande d’annulation d’inscription')
                        ->modalDescription('L’annulation requiert un contrôle à 4 yeux (visa préalable de la Comptabilité puis validation finale du Fondateur).')
                        ->modalSubmitActionLabel('Soumettre la demande')
                        ->visible(fn (Inscription $record): bool => ! in_array($record->statut, ['annulee', 'en_attente_annulation'], true))
                        ->extraModalWindowAttributes(AmbassadorsDesign::goldenModalAttributes('dialog'))
                        ->form([
                            Textarea::make('motif')->label('Motif de la demande d’annulation')->required(),
                        ])
                        ->action(function (Inscription $record, array $data): void {
                            try {
                                app(InscriptionServiceInterface::class)->demanderAnnulation($record->id, $data['motif']);
                                $estComptable = self::estComptable();
                                Notification::make()
                                    ->title($estComptable ? 'Demande visée et transmise au Fondateur' : 'Demande transmise à la Comptabilité')
                                    ->body($estComptable
                                        ? 'Votre visa comptable a été enregistré. La demande attend la validation du Fondateur.'
                                        : 'La demande a été transmise à la Comptabilité pour visa financier préalable.')
                                    ->warning()
                                    ->send();
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()
                                    ->title('Erreur lors de la demande')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->color('gray')
                    ->tooltip('Actions'),
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
                ->formatStateUsing(fn (Inscription $record): string => trim("{$record->eleve?->prenom} {$record->eleve?->nom}"))
                ->searchable(['nom', 'prenom'])
                ->sortable(),
            Tables\Columns\TextColumn::make('classe.nom')->label('Classe')->searchable()->sortable(),
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
                'active' => 'Active',
                'validee' => 'Validée',
                'en_attente_annulation' => 'En attente d’annulation',
                'annulee' => 'Annulée',
                default => $state,
            })
            ->color(fn (string $state): string => match ($state) {
                'en_cours' => 'warning',
                'en_attente_versement' => 'warning',
                'active' => 'success',
                'validee' => 'success',
                'en_attente_annulation' => 'warning',
                'annulee' => 'danger',
                default => 'gray',
            });
    }

    public static function estFondateur(?\App\Models\User $user = null): bool
    {
        $user ??= \Illuminate\Support\Facades\Auth::user();

        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Fondateur', 'Super Admin']);
    }

    public static function estComptable(?\App\Models\User $user = null): bool
    {
        $user ??= \Illuminate\Support\Facades\Auth::user();

        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Comptable', 'Super Admin']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'eleve.parentsTuteurs',
                'classe',
                'anneeScolaire',
                'facturePreinscription.lignes',
            ])
            ->withExists([
                'paiements as a_paiement_valide' => fn ($q) => $q->where('statut', 'valide'),
            ]);
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
            'eleves' => Pages\ListEleves::route('/eleves'),
        ];
    }
}
