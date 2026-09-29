<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Models\User;
use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Contracts\RepartitionPaiementServiceContract;
use App\Modules\Finances\Exceptions\FactureDejaPayeeException;
use App\Modules\Finances\Exceptions\FraisInscriptionNonConfiguresException;
use App\Modules\Finances\Exceptions\ValidationVersementInterditeException;
use App\Modules\Finances\Filament\Resources\FacturePreinscriptionResource;
use App\Modules\Finances\Mail\FactureProvisoireMail;
use App\Modules\Finances\Mail\InscriptionConfirmeeMail;
use App\Modules\Finances\Models\CatalogueFraisDivers;
use App\Modules\Finances\Models\ConfigurationFraisClasse;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Finances\Models\FraisDiversEleve;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\TypeFraisRecurrent;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
    ) {}

    public function getFraisDisponibles(int $niveauId, int $anneeScolaireId, ?int $classeId = null): array
    {
        $types = TypeFraisRecurrent::query()
            ->where(fn ($query) => $query->whereNull('niveau_id')->orWhere('niveau_id', $niveauId))
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->get();
        $inscription = $types->first(fn (TypeFraisRecurrent $type): bool => Str::slug($type->nom) === 'inscription');

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
        return Paiement::query()
            ->where('inscription_id', $inscriptionId)
            ->where('statut', 'valide')
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
        $paiementId = Paiement::query()
            ->where('inscription_id', $inscriptionId)
            ->where('statut', 'valide')
            ->latest('id')
            ->value('id');

        return $paiementId ? $this->paiements->getRecuPdf((int) $paiementId) : null;
    }

    public function erreurConfiguration(int $niveauId, int $anneeScolaireId, array $fraisOptionnels = []): ?string
    {
        $types = $this->typesFactures($fraisOptionnels);
        $typesConfigures = TypeFraisRecurrent::query()
            ->where(fn ($query) => $query->whereNull('niveau_id')->orWhere('niveau_id', $niveauId))
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->get()
            ->map(fn (TypeFraisRecurrent $type): string => Str::slug($type->nom));

        if (! $typesConfigures->contains('inscription')) {
            return (new FraisInscriptionNonConfiguresException)->getMessage();
        }

        return null;
    }

    public function creerFactureProvisoire(int $inscriptionId, int $eleveId, int $niveauId, int $classeId, int $anneeScolaireId, int $parentId, array $fraisOptionnels, ?float $montantVerse, array $identite): object
    {
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
            ->filter(fn (TypeFraisRecurrent $type): bool => in_array(Str::slug($type->nom), $types, true))
            ->keyBy(fn (TypeFraisRecurrent $type): string => Str::slug($type->nom));
        $fraisDivers = CatalogueFraisDivers::query()->where('actif', true)->whereIn('id', $fraisDiversIds)->get();

        if (! $grilles->has('inscription')) {
            throw new FraisInscriptionNonConfiguresException;
        }

        $grilles->get('inscription')->setAttribute(
            'montant',
            (float) config('scolarite.frais_inscription', 15_000),
        );

        $montantScolarite = (float) (ConfigurationFraisClasse::query()
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->value('montant_total') ?? 0);
        $total = round((float) $grilles->sum('montant') + $montantScolarite + (float) $fraisDivers->sum('montant_defaut'), 2);
        $montantVerse ??= $total;
        $minimumInscription = (float) $grilles->get('inscription')->montant;
        if ($montantVerse < $minimumInscription || $montantVerse > $total) {
            throw new \DomainException("Le versement doit être compris entre {$minimumInscription} et {$total} FCFA afin de couvrir au minimum l'inscription.");
        }

        $facture = FacturePreinscription::create([
            'reference' => 'PRE-'.now()->format('YmdHis').'-'.str_pad((string) $inscriptionId, 6, '0', STR_PAD_LEFT),
            'inscription_id' => $inscriptionId,
            'eleve_id' => $eleveId,
            'parent_id' => $parentId,
            'annee_scolaire_id' => $anneeScolaireId,
            'eleve_nom' => $identite['eleve'],
            'parent_nom' => $identite['parent'],
            'parent_email' => $identite['email'],
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
        if ($montantScolarite > 0) {
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
                'obligatoire' => false,
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
        });

        return $facture;
    }

    public function renvoyerFactureProvisoire(int $factureId): object
    {
        $facture = FacturePreinscription::query()->with('lignes.groupe')->findOrFail($factureId);
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
        if (! $comptable?->hasAnyRole(['Comptable', 'Fondateur'])) {
            throw new ValidationVersementInterditeException;
        }

        return DB::transaction(function () use ($factureId, $mode, $referenceTransaction, $montant, $comptable): FacturePreinscription {
            $facture = FacturePreinscription::query()->lockForUpdate()->findOrFail($factureId);
            if ($facture->statut === 'payee') {
                throw new FactureDejaPayeeException($factureId);
            }

            $montantConfirme = $montant ?? (float) ($facture->montant_versement_prevu ?: $facture->montant_total);
            $minimumInscription = (float) $facture->lignes()->where('type_frais', 'inscription')->value('montant');
            if ($montantConfirme < $minimumInscription || $montantConfirme > (float) $facture->montant_total) {
                throw new \DomainException("Le montant reçu doit être compris entre {$minimumInscription} et {$facture->montant_total} FCFA.");
            }
            $repartition = app(RepartitionPaiementServiceContract::class)
                ->repartir($facture->inscription_id, $montantConfirme, $mode, $referenceTransaction);
            $paiement = collect($repartition['lignes'])->first();

            app(InscriptionServiceInterface::class)->validerApresPaiement($facture->inscription_id);
            $facture->update([
                'statut' => 'payee',
                'mode_paiement' => $mode,
                'reference_transaction' => $referenceTransaction,
                'paiement_id' => $paiement?->id,
                'validee_par' => $comptable->id,
                'payee_le' => now(),
            ]);

            $recu = $this->paiements->getRecuPdf((int) $paiement->id);

            DB::afterCommit(fn () => Mail::to($facture->parent_email)->send(new InscriptionConfirmeeMail($facture, $recu)));

            return $facture->refresh();
        });
    }
}
