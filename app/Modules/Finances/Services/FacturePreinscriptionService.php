<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Models\User;
use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Contracts\RepartitionPaiementServiceContract;
use App\Modules\Finances\Exceptions\FactureDejaPayeeException;
use App\Modules\Finances\Exceptions\ValidationVersementInterditeException;
use App\Modules\Finances\Filament\Resources\FacturePreinscriptionResource;
use App\Modules\Finances\Mail\FactureProvisoireMail;
use App\Modules\Finances\Mail\InscriptionConfirmeeMail;
use App\Modules\Finances\Models\CatalogueFraisDivers;
use App\Modules\Finances\Models\ConfigurationFraisClasse;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Finances\Models\FraisDiversEleve;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\PaiementTrancheAllocation;
use App\Modules\Finances\Models\TypeFraisRecurrent;
use App\Modules\Finances\Support\RemiseScolariteCalculator;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Contracts\InscriptionFinanceReadContract;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Modules\Communication\Services\WhatsAppDispatcher;

class FacturePreinscriptionService implements FacturePreinscriptionServiceContract, InscriptionFacturationPort
{
    private const LIBELLES = [
        'inscription' => "Frais d'inscription",
        'scolarite' => 'Frais de scolarité',
        'examen' => "Frais d'examen",
        'transport' => 'Transport scolaire',
        'cantine' => 'Cantine scolaire',
    ];

    public function __construct(
        private readonly PaiementServiceContract $paiements,
        private readonly DocumentServiceContract $documents,
        private readonly AnneeScolaireServiceContract $anneesScolaires,
        private readonly InscriptionFinanceReadContract $inscriptions,
    ) {}

    public function getFraisDisponibles(int $niveauId, int $anneeScolaireId, ?int $classeId = null): array
    {
        $this->assurerFraisInscription($niveauId, $anneeScolaireId);
        $this->assurerFraisDiversParDefaut();
        $types = TypeFraisRecurrent::query()
            ->where(fn ($query) => $query->whereNull('niveau_id')->orWhere('niveau_id', $niveauId))
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->get();
        $inscription = $types->first(fn (TypeFraisRecurrent $type): bool => $type->nature === 'inscription');

        return [
            'obligatoire' => $inscription ? ['code' => 'inscription', 'label' => self::LIBELLES['inscription'], 'montant' => (float) config('scolarite.frais_inscription', 15_000)] : null,
            'optionnels' => CatalogueFraisDivers::query()->where('actif', true)->orderBy('nom')->get()
                ->mapWithKeys(fn (CatalogueFraisDivers $frais): array => ['divers-'.$frais->id => [
                    'label' => $frais->nom,
                    'montant' => (float) $frais->montant_defaut,
                ]])->all(),
            'total_scolarite' => $classeId
                ? (float) (ConfigurationFraisClasse::query()
                    ->where('classe_id', $classeId)
                    ->where('annee_scolaire_id', $anneeScolaireId)
                    ->where('actif', true)
                    ->value('montant_total') ?? 0)
                : (float) $types->where('nature', 'scolarite')->sum('montant'),
        ];
    }

    public function getFactureProvisoire(int $inscriptionId): ?object
    {
        $facture = FacturePreinscription::query()->where('inscription_id', $inscriptionId)->latest('id')->first();

        return $facture ? (object) [
            'id' => (int) $facture->id,
            'reference' => (string) $facture->reference,
            'document_provisoire_id' => $facture->document_provisoire_id ? (int) $facture->document_provisoire_id : null,
            'envoyee_le' => $facture->envoyee_le,
        ] : null;
    }

    public function getResteAPayer(int $inscriptionId): float
    {
        $total = (float) (FacturePreinscription::query()
            ->where('inscription_id', $inscriptionId)
            ->latest('id')
            ->value('montant_total') ?? 0);
        $paye = (float) Paiement::query()
            ->where('inscription_id', $inscriptionId)
            ->where('statut', 'valide')
            ->sum('montant');

        return max(0, round($total - $paye, 2));
    }

    public function getVersements(int $inscriptionId): array
    {
        $inscription = $this->inscriptions->getInfoInscriptionParId($inscriptionId);
        $eleveId = (int) ($inscription['eleve_id'] ?? 0);
        $anneeId = (int) ($inscription['annee_scolaire_id'] ?? 0);

        return Paiement::query()
            ->where('statut', 'valide')
            ->where(function ($q) use ($inscriptionId, $eleveId, $anneeId) {
                $q->where('inscription_id', $inscriptionId);
                if ($eleveId > 0 && $anneeId > 0) {
                    $q->orWhere(fn ($sq) => $sq->where('eleve_id', $eleveId)->where('annee_scolaire_id', $anneeId));
                }
            })
            ->latest('id')
            ->get()
            ->groupBy(fn ($paiement): string => (string) ($paiement->versement_reference ?: $paiement->id))
            ->map(function ($lignes): array {
                $paiement = $lignes->first();

                return [
                    'paiement_id' => (int) $paiement->id,
                    'numero_recu' => (string) $paiement->numero_recu,
                    'montant' => round((float) $lignes->sum('montant'), 2),
                    'mode' => (string) $paiement->mode,
                    'statut' => (string) $paiement->statut,
                ];
            })->values()->all();
    }

    public function getRecuDernierVersement(int $inscriptionId): ?array
    {
        $inscription = $this->inscriptions->getInfoInscriptionParId($inscriptionId);
        $eleveId = (int) ($inscription['eleve_id'] ?? 0);
        $anneeId = (int) ($inscription['annee_scolaire_id'] ?? 0);

        $paiementId = Paiement::query()
            ->where('statut', 'valide')
            ->where(function ($q) use ($inscriptionId, $eleveId, $anneeId) {
                $q->where('inscription_id', $inscriptionId);
                if ($eleveId > 0 && $anneeId > 0) {
                    $q->orWhere(fn ($sq) => $sq->where('eleve_id', $eleveId)->where('annee_scolaire_id', $anneeId));
                }
            })
            ->latest('id')
            ->value('id');

        return $paiementId ? $this->paiements->getRecuPdf((int) $paiementId) : null;
    }

    /** @return array<int, array{id: int, nom: string, montant: float}> */
    public function getFraisDiversSouscrits(int $inscriptionId): array
    {
        $facture = FacturePreinscription::query()
            ->with('lignes')
            ->where('inscription_id', $inscriptionId)
            ->latest('id')
            ->first();

        if ($facture) {
            $divers = $facture->lignes
                ->filter(fn ($l) => str_starts_with((string) $l->type_frais, 'divers-'))
                ->map(fn ($l) => [
                    'id' => (int) str_replace('divers-', '', (string) $l->type_frais),
                    'nom' => (string) $l->libelle,
                    'montant' => (float) $l->montant,
                ])
                ->values()
                ->all();

            if (! empty($divers)) {
                return $divers;
            }
        }

        $inscription = $this->inscriptions->getInfoInscriptionParId($inscriptionId);
        if ($inscription) {
            return FraisDiversEleve::query()
                ->with('poste')
                ->where('eleve_id', $inscription['eleve_id'])
                ->where('annee_scolaire_id', $inscription['annee_scolaire_id'])
                ->where('statut', 'actif')
                ->get()
                ->map(fn ($f) => [
                    'id' => (int) $f->catalogue_frais_divers_id,
                    'nom' => (string) ($f->poste?->nom ?: 'Frais divers'),
                    'montant' => (float) $f->montant,
                ])
                ->values()
                ->all();
        }

        return [];
    }

    public function erreurConfiguration(int $niveauId, int $anneeScolaireId, array $fraisOptionnels = []): ?string
    {
        $this->assurerFraisInscription($niveauId, $anneeScolaireId);

        return null;
    }

    public function creerFactureProvisoire(int $inscriptionId, int $eleveId, int $niveauId, int $classeId, int $anneeScolaireId, int $parentId, array $fraisOptionnels, ?float $montantVerse, array $identite): object
    {
        $this->assurerFraisInscription($niveauId, $anneeScolaireId);
        $types = $this->typesFactures($fraisOptionnels);
        $fraisDiversIds = collect($fraisOptionnels)
            ->filter(fn ($code): bool => str_starts_with((string) $code, 'divers-'))
            ->map(fn ($code): int => (int) Str::after((string) $code, 'divers-'))
            ->filter()->unique()->values();
        $grilles = TypeFraisRecurrent::query()
            ->with('groupe')
            ->where(fn ($query) => $query->whereNull('niveau_id')->orWhere('niveau_id', $niveauId))
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->get()
            ->filter(fn (TypeFraisRecurrent $type): bool => $type->nature === 'inscription' || in_array(Str::slug($type->nom), $types, true))
            ->keyBy(fn (TypeFraisRecurrent $type): string => $type->nature === 'inscription' ? 'inscription' : Str::slug($type->nom));
        $fraisDivers = CatalogueFraisDivers::query()->where('actif', true)->whereIn('id', $fraisDiversIds)->get();

        if (! $grilles->has('inscription')) {
            throw new FraisInscriptionNonConfiguresException;
        }

        $grilles->get('inscription')->setAttribute(
            'montant',
            (float) config('scolarite.frais_inscription', 15_000),
        );

        $montantScolariteBrut = (float) (ConfigurationFraisClasse::query()
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->value('montant_total') ?? 0);
        $montantScolarite = RemiseScolariteCalculator::montantNet($eleveId, $anneeScolaireId, $montantScolariteBrut);
        $totalFraisDivers = (float) $fraisDivers->sum('montant_defaut');
        $total = round((float) $grilles->sum('montant') + $montantScolarite + $totalFraisDivers, 2);
        $montantVerse ??= $total;
        $minimumInscription = (float) $grilles->get('inscription')->montant;
        $minimumRequis = round($minimumInscription + $totalFraisDivers, 2);
        if ($montantVerse < $minimumRequis) {
            $montantFormate = number_format($minimumRequis, 0, ',', ' ');
            if ($totalFraisDivers > 0) {
                throw new \DomainException("Transaction impossible. Le montant minimum à payer est de {$montantFormate} FCFA, comprenant l'inscription et les frais divers sélectionnés.");
            }
            throw new \DomainException("Transaction impossible. Le montant minimum à payer est de {$montantFormate} FCFA, correspondant aux frais d'inscription.");
        }

        if ($montantVerse > $total) {
            $verseFormate = number_format($montantVerse, 0, ',', ' ');
            $totalFormate = number_format($total, 0, ',', ' ');
            $tropPercu = number_format($montantVerse - $total, 0, ',', ' ');
            throw new \DomainException("Transaction impossible. Le montant versé de {$verseFormate} FCFA dépasse le montant total annuel dû ({$totalFormate} FCFA). Monnaie à restituer au parent : {$tropPercu} FCFA.");
        }

        $facture = FacturePreinscription::create([
            'reference' => 'PRE-'.now()->format('YmdHis').'-'.str_pad((string) $inscriptionId, 6, '0', STR_PAD_LEFT),
            'inscription_id' => $inscriptionId,
            'eleve_id' => $eleveId,
            'parent_id' => $parentId,
            'annee_scolaire_id' => $anneeScolaireId,
            'eleve_nom' => $identite['eleve'],
            'parent_nom' => $identite['parent'],
            'parent_email' => $identite['email'] ?? null,
            'parent_telephone' => $identite['telephone'] ?? null,
            'montant_total' => $total,
            'montant_versement_prevu' => round($montantVerse, 2),
            'statut' => 'en_attente_versement',
        ]);

        foreach ($grilles as $type => $grille) {
            $facture->lignes()->create([
                'type_frais' => $type,
                'groupe_frais_id' => $grille->groupe_frais_id,
                'libelle' => self::LIBELLES[$type] ?? $grille->nom,
                'montant' => $grille->montant,
                'obligatoire' => $type === 'inscription',
            ]);
        }
        if ($montantScolariteBrut > 0) {
            $facture->lignes()->create([
                'type_frais' => 'scolarite',
                'groupe_frais_id' => $grilles->get('inscription')->groupe_frais_id,
                'libelle' => 'Scolarité annuelle — 5 tranches',
                'montant' => $montantScolarite,
                'obligatoire' => false,
            ]);
        }
        foreach ($fraisDivers as $frais) {
            $facture->lignes()->create([
                'type_frais' => 'divers-'.$frais->id,
                'groupe_frais_id' => $frais->groupe_frais_id,
                'libelle' => $frais->nom,
                'montant' => $frais->montant_defaut,
                'obligatoire' => true,
            ]);
            FraisDiversEleve::query()->updateOrCreate(
                ['catalogue_frais_divers_id' => $frais->id, 'eleve_id' => $eleveId, 'annee_scolaire_id' => $anneeScolaireId],
                ['montant' => $frais->montant_defaut, 'statut' => 'actif'],
            );
        }

        $facture->load('lignes.groupe');
        $annee = $this->anneesScolaires->getAnneeScolaire($facture->annee_scolaire_id);
        $pdf = Pdf::loadView('finances::pdf.facture-provisoire', compact('facture', 'annee'))->setPaper('a4')->output();
        $document = $this->documents->attacherContenu(
            $facture,
            "facture-provisoire-{$facture->reference}.pdf",
            $pdf,
            'application/pdf',
            'facture_provisoire',
            'interne',
        );
        $facture->update([
            'document_provisoire_id' => $document->id,
        ]);

        DB::afterCommit(function () use ($facture, $pdf): void {
            if ($facture->parent_telephone) {
                try {
                    app(WhatsAppDispatcher::class)->dispatchInscriptionFacture($facture);
                } catch (\Throwable $exception) {
                    report($exception);
                    logger()->warning('Échec de mise en file de la facture d’inscription WhatsApp.', [
                        'facture_id' => $facture->id,
                        'reference' => $facture->reference,
                    ]);
                }
            }
            if (! filter_var($facture->parent_email, FILTER_VALIDATE_EMAIL)) {
                $this->notifierNouvelleInscription($facture);

                return;
            }

            try {
                Mail::to($facture->parent_email)->send(new FactureProvisoireMail($facture, $pdf));
                $facture->update(['envoyee_le' => now()]);
            } catch (\Throwable $exception) {
                report($exception);
                logger()->error('Échec d’envoi de la facture provisoire.', [
                    'facture_id' => $facture->id,
                    'reference' => $facture->reference,
                ]);
                Notification::make()
                    ->title('Facture créée mais e-mail non envoyé')
                    ->body('Utilisez « Renvoyer la facture » depuis les préinscriptions à contrôler.')
                    ->danger()
                    ->persistent()
                    ->send();
            }

            $this->notifierNouvelleInscription($facture);
        });

        return $facture;
    }

    public function renvoyerFactureProvisoire(int $factureId): object
    {
        $facture = FacturePreinscription::query()->with('lignes.groupe')->findOrFail($factureId);
        if (! filter_var($facture->parent_email, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException("Le parent ne possède pas encore d'adresse e-mail valide.");
        }
        $annee = $this->anneesScolaires->getAnneeScolaire($facture->annee_scolaire_id);
        $pdf = Pdf::loadView('finances::pdf.facture-provisoire', compact('facture', 'annee'))->setPaper('a4')->output();

        Mail::to($facture->parent_email)->send(new FactureProvisoireMail($facture, $pdf));
        $facture->update(['envoyee_le' => now()]);

        return $facture->refresh();
    }

    /** @return array<int, string> */
    private function typesFactures(array $fraisOptionnels): array
    {
        return array_values(array_unique([
            'inscription',
            ...collect($fraisOptionnels)
                ->reject(fn ($type): bool => str_starts_with((string) $type, 'divers-'))
                ->map(static fn ($type): string => Str::slug((string) $type))
                ->all(),
        ]));
    }

    public function confirmerVersement(int $factureId, string $mode, ?string $referenceTransaction = null, ?float $montant = null): object
    {
        $comptable = Auth::user();
        if (! $comptable?->hasAnyRole(['Comptable', 'Fondateur', 'Directeur', 'Admin', 'Super Admin'])) {
            throw new ValidationVersementInterditeException;
        }

        return DB::transaction(function () use ($factureId, $mode, $referenceTransaction, $montant, $comptable): FacturePreinscription {
            $facture = FacturePreinscription::query()->lockForUpdate()->findOrFail($factureId);
            if ($facture->statut === 'payee') {
                // Facture déjà confirmée – on renvoie l'instance actuelle sans refacturer.
                return $facture->refresh();
            }

            $montantConfirme = $montant ?? (float) ($facture->montant_versement_prevu ?: $facture->montant_total);
            $minimumInscription = (float) $facture->lignes()->where('type_frais', 'inscription')->value('montant');
            $fraisDiversMontant = (float) $facture->lignes()->where('type_frais', 'like', 'divers-%')->sum('montant');
            $totalFacture = (float) $facture->montant_total;
            $minimumRequis = round($minimumInscription + $fraisDiversMontant, 2);
            if ($montantConfirme < $minimumRequis) {
                $montantFormate = number_format($minimumRequis, 0, ',', ' ');
                if ($fraisDiversMontant > 0) {
                    throw new \DomainException("Transaction impossible. Le montant minimum à payer est de {$montantFormate} FCFA, comprenant l'inscription et les frais divers sélectionnés.");
                }
                throw new \DomainException("Transaction impossible. Le montant minimum à payer est de {$montantFormate} FCFA, correspondant aux frais d'inscription.");
            }

            if ($montantConfirme > $totalFacture) {
                $verseFormate = number_format($montantConfirme, 0, ',', ' ');
                $totalFormate = number_format($totalFacture, 0, ',', ' ');
                $tropPercu = number_format($montantConfirme - $totalFacture, 0, ',', ' ');
                throw new \DomainException("Transaction impossible. Le montant reçu de {$verseFormate} FCFA dépasse le montant total annuel dû ({$totalFormate} FCFA). Monnaie à restituer au parent : {$tropPercu} FCFA.");
            }
            $repartition = app(RepartitionPaiementServiceContract::class)
                ->repartir($facture->inscription_id, $montantConfirme, $mode, $referenceTransaction);
            $paiement = collect($repartition['lignes'])->first();

            $this->inscriptions->validerApresPaiement((int) $facture->inscription_id);
            $facture->update([
                'statut' => 'payee',
                'mode_paiement' => $mode,
                'reference_transaction' => $referenceTransaction,
                'paiement_id' => $paiement?->id,
                'validee_par' => $comptable->id,
                'payee_le' => now(),
            ]);

            $recu = $this->paiements->getRecuPdf((int) $paiement->id);

            if (filter_var($facture->parent_email, FILTER_VALIDATE_EMAIL)) {
                DB::afterCommit(fn () => Mail::to($facture->parent_email)->send(new InscriptionConfirmeeMail($facture, $recu)));
            }

            return $facture->refresh();
        });
    }

    private function notifierNouvelleInscription(FacturePreinscription $facture): void
    {
        $destinataires = User::query()
            ->where('statut', 'actif')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['Comptable', 'Fondateur']))
            ->get();
        Notification::make()
            ->title('Nouvelle inscription en attente de versement')
            ->body("{$facture->eleve_nom} — ".number_format((float) $facture->montant_total, 0, ',', ' ').' FCFA à contrôler.')
            ->icon('heroicon-o-banknotes')
            ->warning()
            ->actions([
                Action::make('controler')->label('Contrôler')->url(FacturePreinscriptionResource::getUrl('index')),
            ])
            ->sendToDatabase($destinataires);
    }

    private function assurerFraisInscription(int $niveauId, int $anneeScolaireId): TypeFraisRecurrent
    {
        $existant = TypeFraisRecurrent::query()
            ->where(fn ($query) => $query->whereNull('niveau_id')->orWhere('niveau_id', $niveauId))
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('nature', 'inscription')
            ->where('actif', true)
            ->first();
        if ($existant) {
            if ((float) $existant->montant !== (float) config('scolarite.frais_inscription', 15_000)) {
                $existant->update(['montant' => (float) config('scolarite.frais_inscription', 15_000)]);
            }

            return $existant;
        }

        $groupeId = DB::table('groupes_frais')->where('code', 'scolarite')->value('id');

        $ancienneConfiguration = TypeFraisRecurrent::query()
            ->where('nom', 'Inscription')
            ->where('niveau_id', $niveauId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->first();
        if ($ancienneConfiguration) {
            $ancienneConfiguration->update([
                'nature' => 'inscription',
                'groupe_frais_id' => $groupeId,
                'montant' => (float) config('scolarite.frais_inscription', 15_000),
                'actif' => true,
            ]);

            return $ancienneConfiguration;
        }

        return TypeFraisRecurrent::query()->create([
            'nom' => 'Inscription',
            'nature' => 'inscription',
            'groupe_frais_id' => $groupeId,
            'niveau_id' => $niveauId,
            'annee_scolaire_id' => $anneeScolaireId,
            'montant' => (float) config('scolarite.frais_inscription', 15_000),
            'actif' => true,
        ]);
    }

    public function assurerFraisDiversParDefaut(): void
    {
        if (CatalogueFraisDivers::query()->where('actif', true)->exists()) {
            return;
        }

        $groupeId = DB::table('groupes_frais')->where('code', 'scolarite')->value('id') ?? 1;

        $frais = [
            ['nom' => 'Tenue scolaire', 'categorie' => 'tenue', 'montant_defaut' => 25000, 'periodicite' => 'unique', 'groupe_frais_id' => $groupeId, 'actif' => true],
            ['nom' => 'Transport scolaire', 'categorie' => 'transport', 'montant_defaut' => 15000, 'periodicite' => 'unique', 'groupe_frais_id' => $groupeId, 'actif' => true],
            ['nom' => 'Full package scolaire', 'categorie' => 'fourniture', 'montant_defaut' => 30000, 'periodicite' => 'unique', 'groupe_frais_id' => $groupeId, 'actif' => true],
            ['nom' => 'Cantine scolaire', 'categorie' => 'autre', 'montant_defaut' => 20000, 'periodicite' => 'unique', 'groupe_frais_id' => $groupeId, 'actif' => true],
            ['nom' => 'Fournitures complémentaires', 'categorie' => 'fourniture', 'montant_defaut' => 10000, 'periodicite' => 'unique', 'groupe_frais_id' => $groupeId, 'actif' => true],
            ['nom' => 'Carnet de correspondance & Badge', 'categorie' => 'autre', 'montant_defaut' => 5000, 'periodicite' => 'unique', 'groupe_frais_id' => $groupeId, 'actif' => true],
            ['nom' => 'Assurance scolaire', 'categorie' => 'autre', 'montant_defaut' => 5000, 'periodicite' => 'unique', 'groupe_frais_id' => $groupeId, 'actif' => true],
        ];

        foreach ($frais as $item) {
            CatalogueFraisDivers::query()->firstOrCreate(
                ['nom' => $item['nom']],
                $item
            );
        }
    }

    public function calculerMontantMinimum(array $fraisOptionnels = []): float
    {
        $this->assurerFraisDiversParDefaut();
        $inscription = (float) config('scolarite.frais_inscription', 15_000);
        $fraisDiversIds = collect($fraisOptionnels)
            ->map(function ($code): int {
                if (is_numeric($code)) {
                    return (int) $code;
                }
                if (str_starts_with((string) $code, 'divers-')) {
                    return (int) Str::after((string) $code, 'divers-');
                }

                return 0;
            })
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($fraisDiversIds->isEmpty()) {
            return round($inscription, 2);
        }

        $totalDivers = (float) CatalogueFraisDivers::query()
            ->where('actif', true)
            ->whereIn('id', $fraisDiversIds)
            ->sum('montant_defaut');

        return round($inscription + $totalDivers, 2);
    }

    /** @return array<string, mixed> */
    public function simulerRepartition(
        float $montantVerse,
        array $fraisOptionnels = [],
        ?int $classeId = null,
        ?int $anneeId = null,
        ?int $eleveId = null,
    ): array {
        $this->assurerFraisDiversParDefaut();
        $fraisInscription = (float) config('scolarite.frais_inscription', 15_000);
        $fraisDiversIds = collect($fraisOptionnels)
            ->map(function ($code): int {
                if (is_numeric($code)) {
                    return (int) $code;
                }
                if (str_starts_with((string) $code, 'divers-')) {
                    return (int) Str::after((string) $code, 'divers-');
                }

                return 0;
            })
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $fraisDiversModels = CatalogueFraisDivers::query()
            ->where('actif', true)
            ->whereIn('id', $fraisDiversIds)
            ->orderBy('nom')
            ->get();

        $totalDivers = (float) $fraisDiversModels->sum('montant_defaut');
        $montantMinimum = round($fraisInscription + $totalDivers, 2);
        $estSuffisant = ($montantVerse >= $montantMinimum);
        $manquant = max(0.0, round($montantMinimum - $montantVerse, 2));

        $reste = max(0.0, $montantVerse);

        // Priorité 1 : Inscription
        $affecteInscription = min($reste, $fraisInscription);
        $statutInscription = 'non_paye';
        if ($affecteInscription >= $fraisInscription && $fraisInscription > 0) {
            $statutInscription = 'couvert';
        } elseif ($affecteInscription > 0) {
            $statutInscription = 'partiel';
        }
        $reste = round(max(0.0, $reste - $affecteInscription), 2);

        // Priorité 2 : Frais divers sélectionnés
        $diversDetails = [];
        $totalDiversAffecte = 0.0;
        foreach ($fraisDiversModels as $frais) {
            $montantPoste = (float) $frais->montant_defaut;
            $affectePoste = min($reste, $montantPoste);
            $statutPoste = 'non_paye';
            if ($affectePoste >= $montantPoste && $montantPoste > 0) {
                $statutPoste = 'couvert';
            } elseif ($affectePoste > 0) {
                $statutPoste = 'partiel';
            }
            $totalDiversAffecte += $affectePoste;
            $reste = round(max(0.0, $reste - $affectePoste), 2);

            $diversDetails[] = [
                'id' => (int) $frais->id,
                'nom' => (string) $frais->nom,
                'montant' => $montantPoste,
                'montant_affecte' => $affectePoste,
                'statut' => $statutPoste,
            ];
        }

        $statutDiversGlobal = 'couvert';
        if ($totalDivers > 0) {
            if ($totalDiversAffecte >= $totalDivers) {
                $statutDiversGlobal = 'couvert';
            } elseif ($totalDiversAffecte > 0) {
                $statutDiversGlobal = 'partiel';
            } else {
                $statutDiversGlobal = 'non_paye';
            }
        }

        // Priorité 3 : Scolarité annuelle (tranches)
        // RÈGLE ABSOLUE : Aucun montant n'est affecté à la scolarité tant que
        // l'inscription et les frais divers sélectionnés ne sont pas entièrement couverts !
        $scolariteAffecte = 0.0;
        $tranchesDetails = [];
        $totalScolarite = 0.0;

        $prerequisSatisfaits = ($affecteInscription >= $fraisInscription) && ($totalDiversAffecte >= $totalDivers);

        if ($classeId && $anneeId) {
            $configuration = ConfigurationFraisClasse::query()
                ->with(['tranches' => fn ($q) => $q->orderBy('ordre')])
                ->where('classe_id', $classeId)
                ->where('annee_scolaire_id', $anneeId)
                ->where('actif', true)
                ->first();

            if ($configuration) {
                $montantNet = (float) $configuration->montant_total;
                if ($eleveId) {
                    $montantNet = RemiseScolariteCalculator::montantNet(
                        $eleveId,
                        $anneeId,
                        $montantNet
                    );
                }
                $totalScolarite = $montantNet;
                $coefficient = (float) $configuration->montant_total > 0
                    ? $montantNet / (float) $configuration->montant_total
                    : 1.0;

                foreach ($configuration->tranches as $tranche) {
                    $montantTranche = round((float) $tranche->montant * $coefficient, 2);
                    $alloc = 0.0;

                    if ($prerequisSatisfaits && $reste > 0) {
                        $alloc = min($reste, $montantTranche);
                        $scolariteAffecte += $alloc;
                        $reste = round(max(0.0, $reste - $alloc), 2);
                    }

                    $statutTranche = 'non_paye';
                    if ($alloc >= $montantTranche && $montantTranche > 0) {
                        $statutTranche = 'couvert';
                    } elseif ($alloc > 0) {
                        $statutTranche = 'partiel';
                    }

                    $tranchesDetails[] = [
                        'id' => (int) $tranche->id,
                        'nom' => (string) ($tranche->libelle ?? $tranche->nom ?? ('Tranche '.$tranche->ordre)),
                        'montant_total' => $montantTranche,
                        'montant_affecte' => $alloc,
                        'statut' => $statutTranche,
                    ];
                }
            }
        }

        $statutScolariteGlobal = 'non_paye';
        if ($totalScolarite > 0) {
            if ($scolariteAffecte >= $totalScolarite) {
                $statutScolariteGlobal = 'couvert';
            } elseif ($scolariteAffecte > 0) {
                $statutScolariteGlobal = 'partiel';
            }
        }

        $totalAffecte = round($affecteInscription + $totalDiversAffecte + $scolariteAffecte, 2);
        $totalAnnuel = round($fraisInscription + $totalDivers + $totalScolarite, 2);
        $depassementTotal = ($totalScolarite > 0 && $montantVerse > $totalAnnuel);
        $tropPercu = $depassementTotal ? round($montantVerse - $totalAnnuel, 2) : 0.0;

        return [
            'montant_verse' => $montantVerse,
            'montant_minimum' => $montantMinimum,
            'total_annuel' => $totalAnnuel,
            'est_suffisant' => $estSuffisant,
            'depassement_total' => $depassementTotal,
            'trop_percu' => $tropPercu,
            'surplus_scolarite' => $scolariteAffecte,
            'manquant' => $manquant,
            'has_divers' => count($diversDetails) > 0,
            'inscription' => [
                'label' => "Frais d'inscription",
                'montant_exige' => $fraisInscription,
                'montant_affecte' => $affecteInscription,
                'statut' => $statutInscription,
            ],
            'frais_divers' => [
                'items' => $diversDetails,
                'total_exige' => $totalDivers,
                'total_affecte' => $totalDiversAffecte,
                'statut' => $statutDiversGlobal,
            ],
            'scolarite' => [
                'montant_total' => $totalScolarite,
                'montant_affecte' => $scolariteAffecte,
                'statut' => $statutScolariteGlobal,
                'tranches' => $tranchesDetails,
            ],
            'total_affecte' => $totalAffecte,
            'reliquat' => $reste,
        ];
    }

    /** @return array<string, mixed> */
    public function getDossierFinancierComplet(int $inscriptionId): array
    {
        $inscription = $this->inscriptions->getInfoInscriptionParId($inscriptionId);
        if (! $inscription) {
            return [];
        }

        $eleveId = (int) $inscription['eleve_id'];
        $classeId = (int) $inscription['classe_id'];
        $anneeId = (int) $inscription['annee_scolaire_id'];

        // 1. Frais d'inscription
        $montantInscription = (float) config('scolarite.frais_inscription', 15_000);
        $payeInscription = (float) Paiement::query()
            ->where('statut', 'valide')
            ->where(function ($q) use ($inscriptionId, $eleveId, $anneeId) {
                $q->where('inscription_id', $inscriptionId);
                if ($eleveId > 0 && $anneeId > 0) {
                    $q->orWhere(fn ($sq) => $sq->where('eleve_id', $eleveId)->where('annee_scolaire_id', $anneeId));
                }
            })
            ->whereIn('rubrique', ['inscription', "Frais d'inscription"])
            ->sum('montant');
        $resteInscription = max(0.0, round($montantInscription - $payeInscription, 2));
        $statutInscription = $payeInscription >= $montantInscription ? 'couvert' : ($payeInscription > 0 ? 'partiel' : 'non_paye');

        // 2. Frais divers souscrits
        $fraisDiversSouscrits = $this->getFraisDiversSouscrits($inscriptionId);
        $fraisDiversDetails = [];
        $totalDiversExige = 0.0;
        $totalDiversPaye = 0.0;

        foreach ($fraisDiversSouscrits as $item) {
            $nom = (string) $item['nom'];
            $montant = (float) $item['montant'];
            $totalDiversExige += $montant;

            $fraisEleveIds = FraisDiversEleve::query()
                ->where('eleve_id', $eleveId)
                ->where('annee_scolaire_id', $anneeId)
                ->where(function ($q) use ($item) {
                    $q->where('catalogue_frais_divers_id', $item['id'])
                        ->orWhereHas('poste', fn ($sq) => $sq->where('nom', $item['nom']));
                })
                ->pluck('id')
                ->values();

            $rubriques = $fraisEleveIds->map(fn ($id) => 'divers:'.$id)->all();
            if ($fraisEleveIds->isEmpty()) {
                $rubriques[] = 'divers:'.$item['id'];
            }

            $paye = (float) Paiement::query()
                ->where('statut', 'valide')
                ->where(function ($q) use ($inscriptionId, $eleveId, $anneeId) {
                    $q->where('inscription_id', $inscriptionId);
                    if ($eleveId > 0 && $anneeId > 0) {
                        $q->orWhere(fn ($sq) => $sq->where('eleve_id', $eleveId)->where('annee_scolaire_id', $anneeId));
                    }
                })
                ->where(function ($q) use ($item, $fraisEleveIds, $rubriques) {
                    if ($fraisEleveIds->isNotEmpty()) {
                        $q->whereIn('frais_divers_eleve_id', $fraisEleveIds)
                            ->orWhereIn('rubrique', $rubriques);
                    } else {
                        $q->whereIn('rubrique', $rubriques)
                            ->orWhereHas('fraisDivers.poste', fn ($sq) => $sq->where('nom', $item['nom']));
                    }
                })
                ->sum('montant');

            $totalDiversPaye += $paye;
            $reste = max(0.0, round($montant - $paye, 2));
            $statutItem = $paye >= $montant ? 'couvert' : ($paye > 0 ? 'partiel' : 'non_paye');

            $fraisDiversDetails[] = [
                'id' => $item['id'],
                'nom' => $nom,
                'montant' => $montant,
                'paye' => $paye,
                'reste' => $reste,
                'statut' => $statutItem,
            ];
        }

        $resteDivers = max(0.0, round($totalDiversExige - $totalDiversPaye, 2));

        // 3. Scolarité annuelle (tranches)
        $scolariteData = [
            'montant_total' => 0.0,
            'total_paye' => 0.0,
            'reste_a_payer' => 0.0,
            'montant_en_retard' => 0.0,
            'tranches' => [],
        ];

        try {
            $scolariteData = app(\App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract::class)
                ->situation($eleveId, $classeId, $anneeId);
        } catch (\Throwable) {
            // Configuration de classe non existante ou non initialisée
        }

        // 4. Totaux généraux & Répartition exacte
        $versements = $this->getVersements($inscriptionId);
        $totalVersements = round((float) collect($versements)->sum('montant'), 2);

        $totalPayeVentile = round($payeInscription + $totalDiversPaye + (float) $scolariteData['total_paye'], 2);
        if ($totalVersements > $totalPayeVentile && (float) $scolariteData['montant_total'] > 0) {
            $surplus = round($totalVersements - ($payeInscription + $totalDiversPaye), 2);
            if ($surplus > (float) $scolariteData['total_paye']) {
                $scolariteData['total_paye'] = min((float) $scolariteData['montant_total'], $surplus);
                $scolariteData['reste_a_payer'] = max(0.0, round((float) $scolariteData['montant_total'] - $scolariteData['total_paye'], 2));
            }
        }

        $totalAnnuel = round($montantInscription + $totalDiversExige + (float) $scolariteData['montant_total'], 2);
        $totalPaye = max($totalVersements, round($payeInscription + $totalDiversPaye + (float) $scolariteData['total_paye'], 2));
        $resteGlobal = max(0.0, round($totalAnnuel - $totalPaye, 2));

        return [
            'inscription' => [
                'label' => "Frais d'inscription",
                'montant' => $montantInscription,
                'paye' => $payeInscription,
                'reste' => $resteInscription,
                'statut' => $statutInscription,
            ],
            'frais_divers' => [
                'items' => $fraisDiversDetails,
                'total_exige' => $totalDiversExige,
                'total_paye' => $totalDiversPaye,
                'reste' => $resteDivers,
            ],
            'scolarite' => $scolariteData,
            'totaux' => [
                'total_annuel' => $totalAnnuel,
                'total_paye' => $totalPaye,
                'reste_global' => $resteGlobal,
                'est_solde' => ($resteGlobal <= 0.0),
            ],
            'facture_provisoire' => $this->getFactureProvisoire($inscriptionId),
            'versements' => $versements,
            'recu_dernier' => $this->getRecuDernierVersement($inscriptionId),
        ];
    }

    /** @return array<string, mixed> */
    public function simulerTransfertFinancier(int $inscriptionId, int $nouvelleClasseId): array
    {
        $dossierActuel = $this->getDossierFinancierComplet($inscriptionId);
        $inscription = $this->inscriptions->getInfoInscriptionParId($inscriptionId);
        if (! $inscription) {
            return [];
        }

        $eleveId = (int) $inscription['eleve_id'];
        $anneeId = (int) $inscription['annee_scolaire_id'];
        $ancienneClasseId = (int) $inscription['classe_id'];

        $ancienneClasse = $this->inscriptions->getInfoClasse($ancienneClasseId);
        $nouvelleClasse = $this->inscriptions->getInfoClasse($nouvelleClasseId);

        $scolariteNouvelle = [
            'montant_total' => 0.0,
            'total_paye' => 0.0,
            'reste_a_payer' => 0.0,
            'tranches' => [],
        ];

        try {
            $scolariteNouvelle = app(\App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract::class)
                ->situation($eleveId, $nouvelleClasseId, $anneeId);
        } catch (\Throwable) {
            // Configuration de classe non initialisée
        }

        $montantInscription = (float) ($dossierActuel['inscription']['montant'] ?? config('scolarite.frais_inscription', 15_000));
        $payeInscription = (float) ($dossierActuel['inscription']['paye'] ?? 0);
        $totalDiversExige = (float) ($dossierActuel['frais_divers']['total_exige'] ?? 0);
        $totalDiversPaye = (float) ($dossierActuel['frais_divers']['total_paye'] ?? 0);
        $totalVersements = (float) ($dossierActuel['totaux']['total_paye'] ?? 0);

        $versementsDisponiblesScolarite = max(0.0, round($totalVersements - ($payeInscription + $totalDiversPaye), 2));

        $nouveauTotalScolarite = (float) ($scolariteNouvelle['montant_total'] ?? 0);
        $nouveauScolaritePaye = min($nouveauTotalScolarite, $versementsDisponiblesScolarite);
        $nouveauScolariteReste = max(0.0, round($nouveauTotalScolarite - $nouveauScolaritePaye, 2));

        $restantAAllouer = $versementsDisponiblesScolarite;
        $tranchesSimulees = [];
        foreach ($scolariteNouvelle['tranches'] ?? [] as $tranche) {
            $montantTranche = (float) ($tranche['montant'] ?? 0);
            $payeTranche = min($restantAAllouer, $montantTranche);
            $resteTranche = max(0.0, round($montantTranche - $payeTranche, 2));
            $restantAAllouer = max(0.0, round($restantAAllouer - $payeTranche, 2));

            $tranchesSimulees[] = [
                'ordre' => $tranche['ordre'] ?? 1,
                'libelle' => $tranche['libelle'] ?? 'Tranche',
                'date_echeance' => $tranche['date_echeance'] ?? null,
                'montant' => $montantTranche,
                'paye' => $payeTranche,
                'reste' => $resteTranche,
                'statut' => $payeTranche >= $montantTranche ? 'payee' : ($payeTranche > 0 ? 'partiellement_payee' : 'a_venir'),
            ];
        }

        $ancienTotalAnnuel = (float) ($dossierActuel['totaux']['total_annuel'] ?? 0);
        $nouveauTotalAnnuel = round($montantInscription + $totalDiversExige + $nouveauTotalScolarite, 2);
        $differenceTotal = round($nouveauTotalAnnuel - $ancienTotalAnnuel, 2);

        $nouveauSoldeRestant = max(0.0, round($nouveauTotalAnnuel - $totalVersements, 2));
        $surplusRemboursable = $totalVersements > $nouveauTotalAnnuel ? round($totalVersements - $nouveauTotalAnnuel, 2) : 0.0;

        return [
            'ancienne_classe' => [
                'id' => $ancienneClasseId,
                'nom' => $ancienneClasse['nom'] ?? '—',
                'scolarite' => (float) ($dossierActuel['scolarite']['montant_total'] ?? 0),
                'total_annuel' => $ancienTotalAnnuel,
                'solde_restant' => (float) ($dossierActuel['totaux']['reste_global'] ?? 0),
            ],
            'nouvelle_classe' => [
                'id' => $nouvelleClasseId,
                'nom' => $nouvelleClasse['nom'] ?? '—',
                'scolarite' => $nouveauTotalScolarite,
                'total_annuel' => $nouveauTotalAnnuel,
                'solde_restant' => $nouveauSoldeRestant,
                'surplus_remboursable' => $surplusRemboursable,
            ],
            'frais_annexes' => [
                'inscription_montant' => $montantInscription,
                'divers_montant' => $totalDiversExige,
                'total' => round($montantInscription + $totalDiversExige, 2),
            ],
            'total_paye' => $totalVersements,
            'difference' => $differenceTotal,
            'tranches' => $tranchesSimulees,
        ];
    }

    public function reallouerApresTransfert(int $inscriptionId, int $nouvelleClasseId): void
    {
        $inscription = $this->inscriptions->getInfoInscriptionParId($inscriptionId);
        if (! $inscription) {
            return;
        }

        $eleveId = (int) $inscription['eleve_id'];
        $anneeId = (int) $inscription['annee_scolaire_id'];

        $nouvelleConfig = ConfigurationFraisClasse::query()
            ->with('tranches')
            ->where('classe_id', $nouvelleClasseId)
            ->where('annee_scolaire_id', $anneeId)
            ->where('actif', true)
            ->first();

        $paiementsScolarite = Paiement::query()
            ->where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeId)
            ->where('rubrique', 'tranches_classe')
            ->where('statut', 'valide')
            ->get();

        PaiementTrancheAllocation::query()
            ->whereIn('paiement_id', $paiementsScolarite->pluck('id'))
            ->delete();

        if ($nouvelleConfig && $nouvelleConfig->tranches->isNotEmpty()) {
            $montantNet = RemiseScolariteCalculator::montantNet($eleveId, $anneeId, (float) $nouvelleConfig->montant_total);
            $coefficient = (float) $nouvelleConfig->montant_total > 0
                ? $montantNet / (float) $nouvelleConfig->montant_total
                : 0.0;

            foreach ($paiementsScolarite as $paiement) {
                $resteAPayerSurCePaiement = (float) $paiement->montant;

                foreach ($nouvelleConfig->tranches->sortBy('ordre') as $tranche) {
                    if ($resteAPayerSurCePaiement <= 0) {
                        break;
                    }

                    $dejaAlloueTranche = (float) PaiementTrancheAllocation::query()
                        ->where('tranche_frais_classe_id', $tranche->id)
                        ->whereHas('paiement', fn ($q) => $q->where('eleve_id', $eleveId)->where('statut', 'valide'))
                        ->sum('montant');

                    $montantTrancheNet = round((float) $tranche->montant * $coefficient, 2);
                    $resteTranche = max(0.0, $montantTrancheNet - $dejaAlloueTranche);

                    if ($resteTranche > 0) {
                        $alloc = min($resteAPayerSurCePaiement, $resteTranche);
                        PaiementTrancheAllocation::create([
                            'paiement_id' => $paiement->id,
                            'tranche_frais_classe_id' => $tranche->id,
                            'montant' => round($alloc, 2),
                        ]);
                        $resteAPayerSurCePaiement = round($resteAPayerSurCePaiement - $alloc, 2);
                    }
                }
            }
        }

        $facture = FacturePreinscription::where('inscription_id', $inscriptionId)->first();
        if ($facture) {
            $ligneScolarite = $facture->lignes()->where('type_frais', 'scolarite')->first();
            $nouveauTotalScolarite = (float) ($nouvelleConfig?->montant_total ?? 0);
            if ($ligneScolarite && $nouveauTotalScolarite > 0) {
                $ligneScolarite->update(['montant' => $nouveauTotalScolarite]);
            }
            $facture->update([
                'montant_total' => (float) $facture->lignes()->sum('montant'),
            ]);
        }
    }
}
