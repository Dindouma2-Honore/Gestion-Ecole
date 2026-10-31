<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\EleveResource\Pages;

use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Filament\Resources\EleveResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEleve extends CreateRecord
{
    protected static string $resource = EleveResource::class;

    protected static ?string $title = 'Nouvelle admission';

    protected bool $inscriptionEffectuee = false;

    protected bool $paiementValide = false;

    protected ?string $nomClasse = null;

    protected float $montantConfirme = 0;

    /**
     * On passe par le Service (donc par le Contract), pour que le statut
     * initial et toute règle future (détection de doublons, etc.) restent
     * centralisés à un seul endroit plutôt que dupliqués dans Filament.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $inscrireImmediatement = ! empty($data['inscrire_immediatement']) && ! empty($data['classe_id']);
        $classeId = ! empty($data['classe_id']) ? (int) $data['classe_id'] : null;
        $fraisOptionnels = array_values((array) ($data['frais_optionnels'] ?? []));
        $montantVerse = (float) ($data['montant_verse'] ?? config('scolarite.frais_inscription', 15_000));
        $modePaiement = (string) ($data['mode_paiement'] ?? 'especes');
        $referenceTransaction = filled($data['reference_transaction'] ?? null) ? (string) $data['reference_transaction'] : null;
        $validerPaiement = ! empty($data['valider_paiement']);

        unset(
            $data['inscrire_immediatement'],
            $data['classe_id'],
            $data['frais_optionnels'],
            $data['montant_verse'],
            $data['mode_paiement'],
            $data['reference_transaction'],
            $data['valider_paiement']
        );

        $resultat = app(EleveServiceInterface::class)->creer($data);
        $eleve = Eleve::findOrFail($resultat['id']);

        if ($inscrireImmediatement && $classeId) {
            $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
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
                classeId: $classeId,
                anneeScolaireId: $anneeId,
                parentId: $parent->id,
                fraisOptionnels: $fraisOptionnels,
                montantVerse: $montantVerse,
            );

            $this->inscriptionEffectuee = true;
            $this->montantConfirme = $montantVerse;

            if ($validerPaiement) {
                $facture = FacturePreinscription::where('inscription_id', $inscriptionId)->first();
                if ($facture) {
                    app(FacturePreinscriptionServiceContract::class)->confirmerVersement(
                        factureId: $facture->id,
                        mode: $modePaiement,
                        referenceTransaction: $referenceTransaction,
                        montant: $montantVerse,
                    );
                    $this->paiementValide = true;
                }
            }
        }

        return $eleve;
    }

    protected function getCreatedNotification(): ?Notification
    {
        if ($this->inscriptionEffectuee) {
            if ($this->paiementValide) {
                return Notification::make()
                    ->title('Admission, inscription et paiement validés !')
                    ->body("L’élève a été admis, inscrit et le versement de ".number_format($this->montantConfirme, 0, ',', ' ')." FCFA a été validé.")
                    ->success();
            }

            return Notification::make()
                ->title('Admission et préinscription enregistrées')
                ->body('L’élève a été admis et sa préinscription a été émise en attente de règlement comptable.')
                ->info();
        }

        return Notification::make()
            ->title('Nouvelle admission enregistrée')
            ->body('L’élève a été admis. Vous pouvez valider son inscription en classe depuis le bouton « Inscrire l’élève » (menu 3 points).')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return EleveResource::getUrl('index');
    }
}
