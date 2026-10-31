<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Models\User;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Contracts\FactureServiceInterface;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Contracts\InscriptionFinanceReadContract;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Exceptions\CapaciteClasseDepasseeException;
use App\Modules\Scolarite\Exceptions\DoubleInscriptionException;
use App\Modules\Scolarite\Exceptions\EleveIntrouvableException;
use App\Modules\Scolarite\Exceptions\ParentPayeurInvalideException;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Scolarite\Models\TransfertAffectation;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InscriptionService implements InscriptionServiceInterface, InscriptionFinanceReadContract
{
    public function __construct(
        private readonly ClasseServiceInterface $classeService,
        private readonly AuditServiceContract $auditService,
        private readonly FactureServiceInterface $factureService,
        // Nullable : le module Finances (Dev B) n'est pas encore livré, donc
        // aucun binding n'existe pour ce port. Rendre le paramètre optionnel
        // permet à InscriptionService de rester instanciable (inscrire(),
        // preparerEtInscrire()) tant que Finances n'est pas prêt.
        private readonly ?InscriptionFacturationPort $facturation = null,
    ) {}

    public function inscrire(int $eleveId, int $classeId, int $anneeScolaireId): int
    {
        return DB::transaction(function () use ($eleveId, $classeId, $anneeScolaireId) {

            $eleve = Eleve::find($eleveId);

            if (! $eleve) {
                throw EleveIntrouvableException::pourId($eleveId);
            }

            if ($this->estInscrit($eleveId, $anneeScolaireId)) {
                throw new DoubleInscriptionException($eleveId, $anneeScolaireId);
            }

            if ($this->classeService->getPlacesRestantes($classeId) <= 0) {
                throw new CapaciteClasseDepasseeException($classeId);
            }

            $type = Inscription::query()->withoutGlobalScopes()->where('eleve_id', $eleveId)->exists()
                ? 'reinscription'
                : 'inscription';

            $existing = Inscription::query()
                ->withoutGlobalScopes()
                ->withTrashed()
                ->where('eleve_id', $eleveId)
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->update([
                    'classe_id' => $classeId,
                    'type' => $type,
                    'date_inscription' => now(),
                    'statut' => 'validee',
                ]);
                $inscription = $existing;
            } else {
                // Utilise firstOrCreate (avec les enregistrements soft‑deleted) pour garantir l'unicité.
                $inscription = Inscription::withTrashed()->firstOrCreate(
                    [
                        'eleve_id' => $eleveId,
                        'annee_scolaire_id' => $anneeScolaireId,
                    ],
                    [
                        'classe_id' => $classeId,
                        'type' => $type,
                        'date_inscription' => now(),
                        'statut' => 'validee',
                    ]
                );

                // Si l’inscription était soft‑deleted, on la restaure.
                if ($inscription->trashed()) {
                    $inscription->restore();
                }

                // Met à jour les champs éventuels.
                $inscription->update([
                    'classe_id' => $classeId,
                    'type' => $type,
                    'date_inscription' => now(),
                    'statut' => 'validee',
                ]);
            }

            $eleve->update(['statut' => 'inscrit']);

            // 4. Journaliser via le Socle — jamais d'écriture directe dans la table audits.
            $this->auditService->enregistrerAvecMotif(
                $inscription,
                "Inscription de l'élève #{$eleveId} en classe #{$classeId}",
                'inscription_initiale'
            );

            // 5. Générer la facture correspondante (montant = frais de la
            // classe). L'envoi au parent par WhatsApp sera branché plus tard
            // par un autre développeur, via FactureServiceInterface — cette
            // étape s'arrête à la génération.
            if (method_exists($this->factureService, 'genererPourInscription')) {
                $this->factureService->genererPourInscription($inscription->id);
            }

            return $inscription->id;
        });
    }

    public function reinscrire(int $eleveId, int $classeId, int $anneeScolaireId, array $fraisDiversIds = []): int
    {
        if ($this->estInscrit($eleveId, $anneeScolaireId)) {
            throw new DoubleInscriptionException($eleveId, $anneeScolaireId);
        }
        if ($this->classeService->getPlacesRestantes($classeId) <= 0) {
            throw new CapaciteClasseDepasseeException($classeId);
        }

        $existing = Inscription::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->update([
                'classe_id' => $classeId,
                'type' => 'reinscription',
                'date_inscription' => now(),
                'statut' => 'en_attente_versement',
            ]);

            return (int) $existing->id;
        }

        // Utilise firstOrCreate (avec les enregistrements soft‑deleted) pour garantir l'unicité sur (eleve_id, annee_scolaire_id) même en cas de double soumission.
        $inscription = Inscription::withTrashed()->firstOrCreate(
            [
                'eleve_id' => $eleveId,
                'annee_scolaire_id' => $anneeScolaireId,
            ],
            [
                'classe_id' => $classeId,
                'type' => 'reinscription',
                'date_inscription' => now(),
                'statut' => 'en_attente_versement',
            ]
        );

        // Si l’inscription était soft‑deleted, on la restaure.
        if ($inscription->trashed()) {
            $inscription->restore();
        }

        return (int) $inscription->id;
    }

    public function demarrerPreinscription(int $eleveId, int $classeId, int $anneeScolaireId, int $parentId, array $fraisOptionnels = [], ?float $montantVerse = null): int
    {
        if (! $this->facturation) {
            throw new \RuntimeException(
                "Le module Finances n'est pas encore disponible : la préinscription avec facture provisoire est désactivée. Utilisez inscrire() ou preparerEtInscrire() pour valider directement."
            );
        }

        return DB::transaction(function () use ($eleveId, $classeId, $anneeScolaireId, $parentId, $fraisOptionnels, $montantVerse): int {
            $eleve = Eleve::with(['parentsTuteurs' => fn ($query) => $query->whereKey($parentId)])->find($eleveId);

            if (! $eleve) {
                throw EleveIntrouvableException::pourId($eleveId);
            }

            $parent = $eleve->parentsTuteurs->first();
            if (! $parent) {
                throw new ParentPayeurInvalideException;
            }

            Inscription::query()
                ->withoutGlobalScopes()
                ->where('eleve_id', $eleveId)
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->lockForUpdate()
                ->get();

            if ($this->estInscrit($eleveId, $anneeScolaireId)) {
                throw new DoubleInscriptionException($eleveId, $anneeScolaireId);
            }

            if ($this->classeService->getPlacesRestantes($classeId) <= 0) {
                throw new CapaciteClasseDepasseeException($classeId);
            }

            $type = Inscription::query()->withoutGlobalScopes()->where('eleve_id', $eleveId)->exists()
                ? 'reinscription'
                : 'inscription';

            $inscription = $this->creerOuReprendreInscription(
                $eleveId,
                $classeId,
                $anneeScolaireId,
                $type,
            );

            // Ne créer la facture provisoire que si elle n'existe pas encore.
            // Protège contre les double-soumissions du formulaire où l'inscription
            // a été persistée mais la notification de succès n'a pas été affichée.
            if (! $this->facturation->getFactureProvisoire($inscription->id)) {
                $this->facturation->creerFactureProvisoire(
                    inscriptionId: $inscription->id,
                    eleveId: $eleveId,
                    niveauId: $this->classeService->getNiveauId($classeId),
                    classeId: $classeId,
                    anneeScolaireId: $anneeScolaireId,
                    parentId: $parentId,
                    fraisOptionnels: $fraisOptionnels,
                    montantVerse: $montantVerse,
                    identite: [
                        'eleve' => trim("{$eleve->prenom} {$eleve->nom}"),
                        'parent' => trim("{$parent->prenom} {$parent->nom}"),
                        'email' => filter_var($parent->email, FILTER_VALIDATE_EMAIL) ? $parent->email : null,
                        'telephone' => $parent->telephone,
                    ],
                );
            }

            $this->auditService->enregistrerAvecMotif(
                $inscription,
                "Préinscription de l'élève #{$eleveId} en attente de versement",
                'facture_provisoire_emise',
            );

            return $inscription->id;
        });
    }

    public function preparerEtDemarrerPreinscription(?int $eleveId, array $eleve, ?int $parentId, array $parent, string $lienParente, int $classeId, int $anneeScolaireId, array $fraisOptionnels = [], ?float $montantVerse = null): int
    {
        return DB::transaction(function () use ($eleveId, $eleve, $parentId, $parent, $lienParente, $classeId, $anneeScolaireId, $fraisOptionnels, $montantVerse): int {
            $dossierEleve = $eleveId
                ? Eleve::query()->findOrFail($eleveId)
                : Eleve::query()->create([...$eleve, 'statut' => 'prospect']);

            $dossierParent = $parentId
                ? ParentTuteur::query()->findOrFail($parentId)
                : ParentTuteur::query()->create(['prenom' => '', ...$parent, 'portail_actif' => true]);

            $dossierEleve->parentsTuteurs()->syncWithoutDetaching([
                $dossierParent->id => [
                    'lien' => $lienParente,
                    'responsable_legal' => true,
                    'responsable_paiement' => true,
                    'autorise_recuperation' => true,
                ],
            ]);

            return $this->demarrerPreinscription(
                $dossierEleve->id,
                $classeId,
                $anneeScolaireId,
                $dossierParent->id,
                $fraisOptionnels,
                $montantVerse,
            );
        });
    }

    /**
     * Même préparation que preparerEtDemarrerPreinscription() (dossier
     * élève + dossier parent, liés), mais valide l'inscription tout de
     * suite via inscrire() au lieu de passer par le module Finances.
     */
    public function preparerEtInscrire(?int $eleveId, array $eleve, ?int $parentId, array $parent, string $lienParente, int $classeId, int $anneeScolaireId): int
    {
        return DB::transaction(function () use ($eleveId, $eleve, $parentId, $parent, $lienParente, $classeId, $anneeScolaireId): int {
            $dossierEleve = $eleveId
                ? Eleve::query()->findOrFail($eleveId)
                : Eleve::query()->create([...$eleve, 'statut' => 'prospect']);

            $dossierParent = $parentId
                ? ParentTuteur::query()->findOrFail($parentId)
                : ParentTuteur::query()->create(['prenom' => '', ...$parent, 'portail_actif' => true]);

            $dossierEleve->parentsTuteurs()->syncWithoutDetaching([
                $dossierParent->id => [
                    'lien' => $lienParente,
                    'responsable_legal' => true,
                    'responsable_paiement' => true,
                    'autorise_recuperation' => true,
                ],
            ]);

            return $this->inscrire($dossierEleve->id, $classeId, $anneeScolaireId);
        });
    }

    public function validerApresPaiement(int $inscriptionId): void
    {
        DB::transaction(function () use ($inscriptionId): void {
            $inscription = Inscription::query()->lockForUpdate()->findOrFail($inscriptionId);
            if ($inscription->statut === 'validee') {
                return;
            }

            $eleve = Eleve::query()->lockForUpdate()->findOrFail($inscription->eleve_id);
            if (empty($eleve->matricule_permanent)) {
                $compteur = CompteurMatricule::query()->where('annee_scolaire_id', $inscription->annee_scolaire_id)->lockForUpdate()->first()
                    ?? CompteurMatricule::create(['annee_scolaire_id' => $inscription->annee_scolaire_id, 'dernier_numero' => 0]);
                $compteur->increment('dernier_numero');
                $eleve->matricule_permanent = 'AMB-'.date('Y').'-'.str_pad((string) $compteur->dernier_numero, 6, '0', STR_PAD_LEFT);
            }

            $eleve->statut = 'inscrit';
            $eleve->save();
            $inscription->update(['statut' => 'validee']);

            $this->auditService->enregistrerAvecMotif($inscription, 'Inscription validée après paiement', 'versement_confirme_par_comptable');
        });
    }

    public function activerApresVersement(int $inscriptionId): void
    {
        $inscription = Inscription::findOrFail($inscriptionId);
        $inscription->update(['statut' => 'active']);
        $inscription->eleve()->update(['statut' => 'actif']);
    }

    /**
     * Une inscription annulée (ou déjà facturée) ne doit pas être recyclée :
     * réutiliser le même id retenterait d'insérer une seconde facture
     * (contrainte UNIQUE inscription_id).
     */
    private function creerOuReprendreInscription(int $eleveId, int $classeId, int $anneeScolaireId, string $type): Inscription
    {
        $existing = Inscription::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->orderByDesc('id')
            ->first();

        $dejaFacturee = $existing
            ? DB::table('factures_preinscription')->where('inscription_id', $existing->id)->exists()
            : false;

        $nePasRecycler = $existing && (
            $existing->statut === 'annulee'
            || $dejaFacturee
        );

        if ($existing && ! $nePasRecycler) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->update([
                'classe_id' => $classeId,
                'type' => $type,
                'date_inscription' => now(),
                'statut' => 'en_attente_versement',
            ]);

            return $existing;
        }

        // Utilise firstOrCreate (avec les enregistrements soft‑deleted) pour garantir l'unicité sur (eleve_id, annee_scolaire_id) même en cas de double soumission.
        $inscription = Inscription::withTrashed()->firstOrCreate(
            [
                'eleve_id' => $eleveId,
                'annee_scolaire_id' => $anneeScolaireId,
            ],
            [
                'classe_id' => $classeId,
                'type' => $type,
                'date_inscription' => now(),
                'statut' => 'en_attente_versement',
            ]
        );

        // Si l’inscription était soft‑deleted, on la restaure.
        if ($inscription->trashed()) {
            $inscription->restore();
        }

        // Met à jour les champs au cas où ils auraient changé.
        $inscription->update([
            'classe_id' => $classeId,
            'type' => $type,
            'date_inscription' => now(),
            'statut' => 'en_attente_versement',
        ]);

        return $inscription;
    }

    public function estInscrit(int $eleveId, int $anneeScolaireId): bool
    {
        return Inscription::query()
            ->withoutGlobalScopes()
            ->where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->whereNotIn('statut', ['annulee'])
            ->exists();
    }

    public function getFraisRestants(int $inscriptionId): float
    {
        // TODO: le module Finances (Dev B) n'est pas encore livré. Une fois
        // FinancesContracts disponible côté Finances, Finances calculera et
        // exposera ce montant lui-même — Scolarité ne doit jamais recalculer
        // les frais. En attendant, valeur neutre pour ne pas bloquer les
        // modules qui consomment déjà ce Contract (ex: Qualité pédagogique).
        return 0.0;
    }

    public function getContexteFinancier(int $inscriptionId): array
    {
        $inscription = Inscription::query()->findOrFail($inscriptionId);

        return [
            'inscription_id' => (int) $inscription->id,
            'eleve_id' => (int) $inscription->eleve_id,
            'classe_id' => (int) $inscription->classe_id,
            'annee_scolaire_id' => (int) $inscription->annee_scolaire_id,
            'statut' => (string) $inscription->statut,
        ];
    }

    public function annulerInscription(int $inscriptionId, string $motif): void
    {
        $user = Auth::user();
        $inscription = Inscription::query()->withoutGlobalScopes()->findOrFail($inscriptionId);

        if (app()->runningInConsole() && ! request()->hasHeader('X-Livewire')) {
            if (! $inscription->aVisaComptable()) {
                $inscription->update([
                    'annulation_visee_par' => $user?->id,
                    'annulation_visee_le' => now(),
                    'visa_comptable_notes' => 'Visa automatique (Console/Service)',
                ]);
                $inscription->refresh();
            }
            $this->validerAnnulation($inscriptionId, $motif, $user?->id);

            return;
        }

        $estFondateur = $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Fondateur', 'Super Admin']);
        $estComptable = $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Comptable', 'Super Admin']);

        if ($estFondateur && $inscription->aVisaComptable()) {
            $this->validerAnnulation($inscriptionId, $motif, $user?->id);
        } elseif ($estComptable) {
            if ($inscription->statut === 'en_attente_annulation') {
                $this->viserAnnulation($inscriptionId, $motif, $user?->id);
            } else {
                $this->demanderAnnulation($inscriptionId, $motif, $user?->id);
            }
        } else {
            $this->demanderAnnulation($inscriptionId, $motif, $user?->id);
        }
    }

    public function demanderAnnulation(int $inscriptionId, string $motif, ?int $demandeurId = null): void
    {
        $motif = trim($motif);
        if ($motif === '') {
            throw new \InvalidArgumentException("Le motif d'annulation est obligatoire.");
        }

        DB::transaction(function () use ($inscriptionId, $motif, $demandeurId): void {
            $inscription = Inscription::query()->withoutGlobalScopes()->with(['eleve', 'classe'])->findOrFail($inscriptionId);

            if ($inscription->statut === 'annulee') {
                throw new \DomainException('Cette inscription est déjà annulée.');
            }
            if ($inscription->statut === 'en_attente_annulation') {
                throw new \DomainException('Une demande d’annulation est déjà en cours d’examen pour cette inscription.');
            }

            $demandeurId ??= Auth::id();
            $demandeur = $demandeurId ? User::find($demandeurId) : Auth::user();
            $estComptable = $demandeur && method_exists($demandeur, 'hasAnyRole') && $demandeur->hasAnyRole(['Comptable', 'Super Admin']);

            $updateData = [
                'statut' => 'en_attente_annulation',
                'motif_annulation' => $motif,
                'annulation_demandee_par' => $demandeurId,
                'annulation_demandee_le' => now(),
            ];

            if ($estComptable) {
                // La comptabilité est l'initiatrice -> visa comptable posé automatiquement
                $updateData['annulation_visee_par'] = $demandeurId;
                $updateData['annulation_visee_le'] = now();
                $updateData['visa_comptable_notes'] = 'Visa automatique apposé lors de la demande par la Comptabilité';
            }

            $inscription->update($updateData);

            $this->auditService->enregistrerAvecMotif(
                $inscription,
                "Demande d'annulation d'inscription #{$inscriptionId}".($estComptable ? ' (avec visa comptable)' : ''),
                $motif
            );

            $demandeurNom = $demandeur?->name ?? 'Un membre du personnel';

            if ($estComptable) {
                // Notifier directement le Fondateur car le visa financier est déjà en règle
                $fondateurs = User::query()
                    ->where('statut', 'actif')
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Fondateur', 'Super Admin']))
                    ->get();

                if ($fondateurs->isNotEmpty()) {
                    Notification::make()
                        ->title('Annulation visée par la Comptabilité à valider')
                        ->body("La Comptabilité ({$demandeurNom}) a visé et soumis une demande d’annulation pour {$inscription->eleve->nom} {$inscription->eleve->prenom} ({$inscription->classe->nom}). En attente de validation.")
                        ->icon('heroicon-o-shield-check')
                        ->warning()
                        ->actions([
                            Action::make('voir')->label('Consulter le dossier')->url(InscriptionResource::getUrl('view', ['record' => $inscription->id])),
                        ])
                        ->sendToDatabase($fondateurs);
                }
            } else {
                // Notifier les agents de la Comptabilité pour le premier œil (contrôle financier)
                $comptables = User::query()
                    ->where('statut', 'actif')
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Comptable', 'Super Admin']))
                    ->get();

                if ($comptables->isNotEmpty()) {
                    Notification::make()
                        ->title('Demande d’annulation à viser par la Comptabilité')
                        ->body("{$demandeurNom} a demandé l’annulation de l’inscription de {$inscription->eleve->nom} {$inscription->eleve->prenom} ({$inscription->classe->nom}). Le visa financier comptable est requis.")
                        ->icon('heroicon-o-currency-dollar')
                        ->warning()
                        ->actions([
                            Action::make('voir')->label('Consulter le dossier')->url(InscriptionResource::getUrl('view', ['record' => $inscription->id])),
                        ])
                        ->sendToDatabase($comptables);
                }
            }
        });
    }

    public function viserAnnulation(int $inscriptionId, ?string $notes = null, ?int $comptableId = null): void
    {
        $comptableId ??= Auth::id();
        $user = $comptableId ? User::find($comptableId) : Auth::user();

        if ($user && method_exists($user, 'hasAnyRole') && ! $user->hasAnyRole(['Comptable', 'Super Admin'])) {
            throw new \DomainException('Seul un agent de la Comptabilité est habilité à viser l’annulation d’une inscription.');
        }

        DB::transaction(function () use ($inscriptionId, $notes, $comptableId): void {
            $inscription = Inscription::query()->withoutGlobalScopes()->with(['eleve', 'classe'])->findOrFail($inscriptionId);

            if ($inscription->statut !== 'en_attente_annulation') {
                throw new \DomainException('Seule une inscription en attente d’annulation peut recevoir un visa comptable.');
            }

            if ($inscription->aVisaComptable()) {
                throw new \DomainException('Cette demande d’annulation dispose déjà d’un visa comptable.');
            }

            $notesFinales = trim((string) ($notes ?: 'Visa financier accordé par la Comptabilité'));

            $inscription->update([
                'annulation_visee_par' => $comptableId,
                'annulation_visee_le' => now(),
                'visa_comptable_notes' => $notesFinales,
            ]);

            $this->auditService->enregistrerAvecMotif(
                $inscription,
                "Visa comptable apposé pour l'annulation d'inscription #{$inscriptionId}",
                $notesFinales
            );

            // Alerter le Fondateur (2e œil) pour validation définitive
            $fondateurs = User::query()
                ->where('statut', 'actif')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Fondateur', 'Super Admin']))
                ->get();

            if ($fondateurs->isNotEmpty()) {
                $comptable = $comptableId ? User::find($comptableId) : null;
                $comptableNom = $comptable?->name ?? 'La Comptabilité';
                Notification::make()
                    ->title('Demande d’annulation visée par la Comptabilité')
                    ->body("{$comptableNom} a apposé son visa comptable sur l’annulation de {$inscription->eleve->nom} {$inscription->eleve->prenom} ({$inscription->classe->nom}). L’opération est prête pour votre validation.")
                    ->icon('heroicon-o-check-badge')
                    ->warning()
                    ->actions([
                        Action::make('voir')->label('Consulter et valider')->url(InscriptionResource::getUrl('view', ['record' => $inscription->id])),
                    ])
                    ->sendToDatabase($fondateurs);
            }
        });
    }

    public function validerAnnulation(int $inscriptionId, ?string $motifValidation = null, ?int $validateurId = null): void
    {
        $validateurId ??= Auth::id();
        $user = $validateurId ? User::find($validateurId) : Auth::user();

        // En environnement web/authentifié, seul le Fondateur ou Super Admin peut valider
        if ($user && method_exists($user, 'hasAnyRole') && ! $user->hasAnyRole(['Fondateur', 'Super Admin'])) {
            throw new \DomainException('Seul le Fondateur est habilité à valider l’annulation d’une inscription.');
        }

        DB::transaction(function () use ($inscriptionId, $motifValidation, $validateurId): void {
            $inscription = Inscription::query()->withoutGlobalScopes()->with(['eleve', 'classe'])->findOrFail($inscriptionId);

            if ($inscription->statut === 'annulee') {
                throw new \DomainException('Cette inscription est déjà annulée.');
            }

            // Contrôle à 4 yeux : le visa préalable de la Comptabilité est obligatoire
            if (! $inscription->aVisaComptable()) {
                throw new \DomainException('L’annulation nécessite impérativement le visa préalable de la Comptabilité (contrôle à 4 yeux).');
            }

            $motifFinal = trim((string) ($motifValidation ?: $inscription->motif_annulation));
            if ($motifFinal === '') {
                $motifFinal = 'Annulation validée par le Fondateur';
            }

            $inscription->update([
                'statut' => 'annulee',
                'motif_annulation' => $motifFinal,
                'annulation_validee_par' => $validateurId,
                'annulation_validee_le' => now(),
            ]);

            // Si l'élève n'a plus d'autre inscription active, remettre son statut à 'admis'
            $aAutreInscription = Inscription::query()
                ->withoutGlobalScopes()
                ->where('eleve_id', $inscription->eleve_id)
                ->whereNotIn('statut', ['annulee'])
                ->where('id', '!=', $inscriptionId)
                ->exists();

            if (! $aAutreInscription) {
                $inscription->eleve()->update(['statut' => 'admis']);
            }

            // Supprimer la facture provisoire en attente s'il y en a une
            if (class_exists(FacturePreinscription::class)) {
                FacturePreinscription::query()
                    ->where('inscription_id', $inscriptionId)
                    ->where('statut', 'en_attente_versement')
                    ->delete();
            }

            $this->auditService->enregistrerAvecMotif(
                $inscription,
                "Annulation de l'inscription #{$inscriptionId} validée par le Fondateur",
                $motifFinal
            );

            // Notifier le demandeur initial si différent du validateur
            if ($inscription->annulation_demandee_par && $inscription->annulation_demandee_par !== $validateurId) {
                $demandeur = User::find($inscription->annulation_demandee_par);
                if ($demandeur) {
                    Notification::make()
                        ->title('Annulation d’inscription validée')
                        ->body("Le Fondateur a validé l’annulation de l’inscription de {$inscription->eleve->nom} {$inscription->eleve->prenom}.")
                        ->icon('heroicon-o-check-circle')
                        ->success()
                        ->sendToDatabase($demandeur);
                }
            }

            // Notifier la Comptabilité de la finalisation
            if ($inscription->annulation_visee_par && $inscription->annulation_visee_par !== $validateurId) {
                $comptable = User::find($inscription->annulation_visee_par);
                if ($comptable) {
                    Notification::make()
                        ->title('Annulation d’inscription finalisée')
                        ->body("L’annulation de l’inscription de {$inscription->eleve->nom} {$inscription->eleve->prenom} a été validée définitivement par le Fondateur.")
                        ->icon('heroicon-o-check-circle')
                        ->success()
                        ->sendToDatabase($comptable);
                }
            }
        });
    }

    public function rejeterAnnulation(int $inscriptionId, string $motifRejet, ?int $validateurId = null): void
    {
        $validateurId ??= Auth::id();
        $user = $validateurId ? User::find($validateurId) : Auth::user();

        if ($user && method_exists($user, 'hasAnyRole') && ! $user->hasAnyRole(['Fondateur', 'Super Admin'])) {
            throw new \DomainException('Seul le Fondateur est habilité à rejeter une demande d’annulation d’inscription.');
        }

        DB::transaction(function () use ($inscriptionId, $motifRejet): void {
            $inscription = Inscription::query()->withoutGlobalScopes()->with(['eleve', 'classe'])->findOrFail($inscriptionId);

            if ($inscription->statut !== 'en_attente_annulation') {
                throw new \DomainException('Seule une inscription en attente d’annulation peut être rejetée.');
            }

            $inscription->update([
                'statut' => 'active',
                'motif_annulation' => null,
                'annulation_demandee_par' => null,
                'annulation_demandee_le' => null,
                'annulation_visee_par' => null,
                'annulation_visee_le' => null,
                'visa_comptable_notes' => null,
            ]);

            $this->auditService->enregistrerAvecMotif(
                $inscription,
                "Rejet de la demande d'annulation d'inscription #{$inscriptionId} par le Fondateur",
                $motifRejet
            );

            // Notifier le demandeur du rejet
            if ($inscription->annulation_demandee_par) {
                $demandeur = User::find($inscription->annulation_demandee_par);
                if ($demandeur) {
                    Notification::make()
                        ->title('Demande d’annulation rejetée')
                        ->body("Le Fondateur a rejeté la demande d’annulation pour {$inscription->eleve->nom} {$inscription->eleve->prenom}. Motif : {$motifRejet}")
                        ->icon('heroicon-o-x-circle')
                        ->danger()
                        ->sendToDatabase($demandeur);
                }
            }
        });
    }

    public function transfererClasse(int $inscriptionId, int $nouvelleClasseId, string $motif, ?string $dateTransfert = null): TransfertAffectation
    {
        $motif = trim($motif);
        if ($motif === '') {
            throw new \InvalidArgumentException('Le motif du transfert est obligatoire.');
        }

        return DB::transaction(function () use ($inscriptionId, $nouvelleClasseId, $motif, $dateTransfert): TransfertAffectation {
            $inscription = Inscription::query()->withoutGlobalScopes()->with(['eleve', 'classe'])->findOrFail($inscriptionId);

            if ($inscription->statut === 'annulee') {
                throw new \DomainException('Impossible de transférer une inscription annulée.');
            }

            if ($inscription->classe_id === $nouvelleClasseId) {
                throw new \DomainException('L’élève est déjà affecté à cette classe.');
            }

            if ($this->classeService->getPlacesRestantes($nouvelleClasseId) <= 0) {
                throw new CapaciteClasseDepasseeException($nouvelleClasseId);
            }

            $ancienneClasseId = (int) $inscription->classe_id;
            $simulation = [];
            if ($this->facturation) {
                $simulation = $this->facturation->simulerTransfertFinancier($inscriptionId, $nouvelleClasseId);
            }

            $montantScolariteOrigine = (float) ($simulation['ancienne_classe']['scolarite'] ?? 0);
            $montantScolariteDestination = (float) ($simulation['nouvelle_classe']['scolarite'] ?? 0);
            $soldeAvant = (float) ($simulation['ancienne_classe']['solde_restant'] ?? 0);
            $soldeApres = (float) ($simulation['nouvelle_classe']['solde_restant'] ?? 0);

            $inscription->update([
                'classe_id' => $nouvelleClasseId,
            ]);

            if ($this->facturation) {
                $this->facturation->reallouerApresTransfert($inscriptionId, $nouvelleClasseId);
            }

            $transfert = TransfertAffectation::create([
                'inscription_id' => $inscription->id,
                'eleve_id' => $inscription->eleve_id,
                'classe_origine_id' => $ancienneClasseId,
                'classe_destination_id' => $nouvelleClasseId,
                'annee_scolaire_id' => $inscription->annee_scolaire_id,
                'date_transfert' => $dateTransfert ? Carbon::parse($dateTransfert) : now(),
                'motif' => $motif,
                'effectue_par' => Auth::id(),
                'montant_scolarite_origine' => $montantScolariteOrigine,
                'montant_scolarite_destination' => $montantScolariteDestination,
                'solde_avant_transfert' => $soldeAvant,
                'solde_apres_transfert' => $soldeApres,
            ]);

            $this->auditService->enregistrerAvecMotif(
                $inscription,
                "Transfert de classe : #{$ancienneClasseId} -> #{$nouvelleClasseId}",
                $motif
            );

            return $transfert;
        });
    }

    public function getElevesInscritsOptions(int $anneeScolaireId): array
    {
        return Inscription::query()
            ->with('eleve')
            ->whereIn('statut', ['active', 'validee', 'en_attente_versement'])
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->get()
            ->filter(fn ($inscription) => $inscription->eleve !== null)
            ->mapWithKeys(fn ($inscription): array => [
                $inscription->eleve->id => trim($inscription->eleve->nom.' '.$inscription->eleve->prenom).' — '.$inscription->eleve->matricule_permanent,
            ])
            ->all();
    }

    public function getInscriptionsOptions(int $anneeScolaireId): array
    {
        return Inscription::query()->with('eleve', 'classe')
            ->whereIn('statut', ['active', 'validee', 'en_attente_versement'])
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->get()->filter(fn ($i) => $i->eleve !== null)
            ->mapWithKeys(fn ($i): array => [$i->id => trim($i->eleve->nom.' '.$i->eleve->prenom).' — '.($i->classe?->nom ?? 'Sans classe')])
            ->all();
    }

    public function getInfoInscriptionParId(int $inscriptionId): ?array
    {
        $inscription = Inscription::query()->with(['classe', 'eleve.parentsTuteurs'])->find($inscriptionId);
        if (! $inscription) return null;
        return $this->projectionFinance($inscription);
    }

    public function getInscriptionsPourAnnee(int $anneeScolaireId, array $statuts = ['active', 'validee']): array
    {
        return Inscription::query()->with(['classe', 'eleve'])
            ->where('annee_scolaire_id', $anneeScolaireId)->whereIn('statut', $statuts)->get()
            ->filter(fn ($i) => $i->eleve !== null)
            ->map(fn ($i): array => ['id'=>(int)$i->id,'classe_id'=>(int)$i->classe_id,'classe_nom'=>(string)($i->classe?->nom ?? ''),'eleve_id'=>(int)$i->eleve_id,'eleve_nom'=>(string)$i->eleve->nom,'eleve_prenom'=>(string)$i->eleve->prenom,'matricule_permanent'=>$i->eleve->matricule_permanent,'annee_scolaire_id'=>(int)$i->annee_scolaire_id,'statut'=>(string)$i->statut])
            ->all();
    }

    public function getInfoClasse(int $classeId): ?array
    {
        $classe = $this->classeService->getNomClasse($classeId);
        return $classe !== '' ? ['id'=>$classeId, 'nom'=>$classe] : null;
    }

    public function getInfoInscription(?int $inscriptionId, int $eleveId, int $anneeScolaireId): ?array
    {
        $query = Inscription::query()->with(['classe', 'eleve.parentsTuteurs']);

        if ($inscriptionId) {
            $inscription = $query->find($inscriptionId);
        } else {
            $inscription = $query->where('eleve_id', $eleveId)
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->whereIn('statut', ['active', 'validee', 'en_attente_versement'])
                ->latest('id')
                ->first();
        }

        if (! $inscription) {
            return null;
        }

        return $this->projectionFinance($inscription);
    }

    /** @return array<string, mixed> */
    private function projectionFinance(Inscription $inscription): array
    {
        $parent = $inscription->eleve?->parentsTuteurs?->sortByDesc(fn ($p) => $p->pivot->responsable_paiement ?? false)->first();
        $annee = app(AnneeScolaireServiceContract::class)->getAnneeScolaire($inscription->annee_scolaire_id);

        return [
            'id' => $inscription->id,
            'statut' => (string) $inscription->statut,
            'classe_id' => $inscription->classe_id,
            'eleve_id' => $inscription->eleve_id,
            'annee_scolaire_id' => $inscription->annee_scolaire_id,
            'classe' => $inscription->classe ? (object) [
                'id' => $inscription->classe->id,
                'nom' => $inscription->classe->nom,
            ] : null,
            'anneeScolaire' => $annee,
            'eleve' => $inscription->eleve ? (object) [
                'id' => $inscription->eleve->id,
                'nom' => $inscription->eleve->nom,
                'prenom' => $inscription->eleve->prenom,
                'matricule_permanent' => $inscription->eleve->matricule_permanent,
            ] : null,
            'parent_principal_nom' => $parent?->nom ?? null,
            'parent_principal_telephone' => $parent?->telephone ?? null,
        ];
    }
}
