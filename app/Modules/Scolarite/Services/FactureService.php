<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\FactureServiceInterface;
use App\Modules\Scolarite\Contracts\FraisServiceContract;
use App\Modules\Scolarite\Exceptions\FactureDejaGenereeException;
use App\Modules\Scolarite\Exceptions\FactureIntrouvableException;
use App\Modules\Scolarite\Mail\FactureProvisoireMail;
use App\Modules\Scolarite\Models\FactureGeneree;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FactureService implements FactureServiceInterface
{
    public function __construct(
        private readonly FraisServiceContract $fraisService,
        private readonly AuditServiceContract $auditService,
        private readonly ParametrageServiceContract $parametrageService,
        private readonly AnneeScolaireServiceContract $anneeScolaireService,
        private readonly DocumentServiceContract $documentService,
    ) {}

    public function genererProvisoire(int $inscriptionId): int
    {
        $facture = DB::transaction(function () use ($inscriptionId): FactureGeneree {
            if (FactureGeneree::where('inscription_id', $inscriptionId)->where('type', 'provisoire')->exists()) {
                throw new FactureDejaGenereeException($inscriptionId);
            }

            $facture = $this->creerFacture($inscriptionId, 'provisoire');

            // Événement système routinier (pas une annulation) : enregistrer(),
            // pas enregistrerAvecMotif() — voir AuditServiceContract.
            $this->auditService->enregistrer(
                $facture,
                "Facture provisoire {$facture->numero} générée pour l'inscription #{$inscriptionId}",
            );

            return $facture;
        });

        // Envoi automatique par e-mail au parent (Module 5 §1), différé
        // jusqu'au commit effectif de la transaction englobante
        // (InscriptionService::inscrire()) : un problème SMTP ne doit
        // jamais faire échouer l'inscription, et on n'envoie jamais un
        // e-mail pour une inscription qui serait finalement annulée par un
        // rollback plus haut dans la pile.
        DB::afterCommit(fn () => $this->envoyerParEmail($facture));

        return $facture->id;
    }

    public function genererDefinitive(int $inscriptionId): int
    {
        return DB::transaction(function () use ($inscriptionId): int {
            if (FactureGeneree::where('inscription_id', $inscriptionId)->where('type', 'definitive')->exists()) {
                throw new FactureDejaGenereeException($inscriptionId);
            }

            $facture = $this->creerFacture($inscriptionId, 'definitive');

            $this->auditService->enregistrer(
                $facture,
                "Facture définitive {$facture->numero} générée pour l'inscription #{$inscriptionId}",
            );

            return $facture->id;
        });
    }

    private function creerFacture(int $inscriptionId, string $type): FactureGeneree
    {
        $inscription = Inscription::with(['eleve.parentsTuteurs', 'classe'])->findOrFail($inscriptionId);
        $eleve = $inscription->eleve;

        // Parent payeur : celui marqué "responsable_paiement" en priorité,
        // sinon le premier parent lié.
        $parent = $eleve->parentsTuteurs->firstWhere('pivot.responsable_paiement', true)
            ?? $eleve->parentsTuteurs->first();

        $montant = $this->fraisService->getMontantDu($inscriptionId);

        // Numérotation déléguée à Socle (ParametrageServiceContract) : la
        // définitive réutilise le type 'facture' pré-semé par Socle
        // (établissement-wide, aux côtés de 'bulletin'/'carte_scolaire' —
        // manifestement prévus pour ce module), la provisoire son propre
        // type 'facture_provisoire_scolarite' (document non fiscal, jamais
        // le même compteur que la définitive). L'année scolaire est celle
        // de l'inscription, pas nécessairement l'année active courante.
        $anneeScolaire = $this->anneeScolaireService->getAnneeScolaire($inscription->annee_scolaire_id);
        $typeDocument = $type === 'definitive' ? 'facture' : 'facture_provisoire_scolarite';
        $numero = $this->parametrageService->genererNumero($typeDocument, [
            'ANNEE_SCOLAIRE' => $anneeScolaire->libelle,
        ]);

        $facture = FactureGeneree::create([
            'inscription_id' => $inscription->id,
            'type' => $type,
            'numero' => $numero,
            'montant' => $montant,
            'date_emission' => now(),
            'parent_id' => $parent?->id,
            'nom_destinataire' => $parent ? trim("{$parent->prenom} {$parent->nom}") : null,
            'telephone_destinataire' => $parent?->telephone,
            'email_destinataire' => $parent?->email,
            'statut_envoi' => 'en_attente',
        ]);

        // Archive durable de la facture (DocumentServiceContract) — même
        // gabarit que l'impression à la volée (ImprimerFactureController),
        // stocké ici en HTML (attacherContenu() accepte n'importe quel
        // mimeType, aucune dépendance à une bibliothèque PDF non confirmée).
        $facture->setRelation('inscription', $inscription);
        $document = $this->documentService->attacherContenu(
            $facture,
            "Facture {$facture->numero}.html",
            view('scolarite::filament.facture-impression', [
                'facture' => $facture,
                'detailFrais' => $this->fraisService->getDetailFrais($inscription->id),
            ])->render(),
            'text/html',
            'facture_scolarite',
        );
        $facture->update(['document_id' => $document->id]);

        return $facture;
    }

    private function envoyerParEmail(FactureGeneree $facture): void
    {
        if (! $facture->email_destinataire) {
            return;
        }

        try {
            Mail::to($facture->email_destinataire)->send(new FactureProvisoireMail($facture));
            $this->marquerEnvoyee($facture->id, 'email');
        } catch (\Throwable $e) {
            Log::warning("Échec d'envoi de la facture provisoire {$facture->numero} par e-mail", [
                'facture_id' => $facture->id,
                'exception' => $e->getMessage(),
            ]);
            $this->marquerEchecEnvoi($facture->id, "Envoi e-mail impossible : {$e->getMessage()}");
        }
    }

    public function getFacture(int $factureId): array
    {
        $facture = FactureGeneree::find($factureId);

        if (! $facture) {
            throw FactureIntrouvableException::pourId($factureId);
        }

        return [
            'id' => $facture->id,
            'type' => $facture->type,
            'numero' => $facture->numero,
            'montant' => (float) $facture->montant,
            'statut_envoi' => $facture->statut_envoi,
            'telephone_destinataire' => $facture->telephone_destinataire,
            'nom_destinataire' => $facture->nom_destinataire,
        ];
    }

    public function marquerEnvoyee(int $factureId, string $canal = 'whatsapp'): void
    {
        $facture = FactureGeneree::find($factureId);

        if (! $facture) {
            throw FactureIntrouvableException::pourId($factureId);
        }

        $facture->update([
            'statut_envoi' => 'envoyee',
            'canal_envoi' => $canal,
            'envoyee_le' => now(),
            'echec_raison' => null,
        ]);

        $this->auditService->enregistrer(
            $facture,
            "Facture {$facture->numero} envoyée au parent via {$canal}",
        );
    }

    public function marquerEchecEnvoi(int $factureId, string $raison): void
    {
        $facture = FactureGeneree::find($factureId);

        if (! $facture) {
            throw FactureIntrouvableException::pourId($factureId);
        }

        $facture->update([
            'statut_envoi' => 'echec',
            'echec_raison' => $raison,
        ]);

        $this->auditService->enregistrer(
            $facture,
            "Échec d'envoi de la facture {$facture->numero} : {$raison}",
        );
    }
}
