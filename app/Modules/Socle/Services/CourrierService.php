<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Communication\Contracts\CourrierNotificationServiceContract;
use App\Modules\Scolarite\Contracts\CourrierDestinataireServiceContract;
use App\Modules\Socle\Contracts\CourrierServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use App\Modules\Socle\Models\Courrier;
use App\Modules\Socle\Models\CourrierDestinataire;
use App\Modules\Socle\Models\CourrierModele;
use App\Modules\Socle\Models\Groupe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourrierService implements CourrierServiceContract
{
    public function __construct(
        private ParametrageServiceContract $parametrage,
        private CourrierDestinataireServiceContract $destinatairesScolarite,
        private CourrierNotificationServiceContract $notifications,
    ) {}

    public function enregistrerCourrierEntrant(array $donnees): object
    {
        $numero = $this->parametrage->genererNumero('courrier', [
            'annee' => (string) now()->year,
        ]);

        return Courrier::create(array_merge($donnees, [
            'numero' => $numero,
            'type' => 'entrant',
            'statut' => 'recu',
        ]));
    }

    public function preparerCourrierSortant(array $donnees): object
    {
        return DB::transaction(function () use ($donnees): Courrier {
            $cible = (string) ($donnees['cible_type'] ?? 'utilisateurs');
            $config = (array) ($donnees['cible_config'] ?? []);
            $contacts = $this->resoudreContacts($cible, $config);
            if ($contacts === []) {
                throw new \DomainException('Aucun destinataire concret ne correspond à la sélection.');
            }

            $courrier = Courrier::create([
                ...$donnees,
                'numero' => $this->parametrage->genererNumero('courrier', ['annee' => (string) now()->year]),
                'type' => 'sortant',
                'statut' => 'recu',
                'destinataire' => count($contacts).' destinataire(s)',
                'cible_config' => $config,
            ]);

            foreach ($contacts as $contact) {
                $courrier->destinataires()->create([
                    'destinataire_type' => $contact['type'],
                    'destinataire_id' => $contact['id'],
                    'nom' => $contact['nom'],
                    'email' => $contact['email'] ?? null,
                    'telephone' => $contact['telephone'] ?? null,
                ]);
            }

            return $courrier->load('destinataires');
        });
    }

    public function envoyerCourrier(int $courrierId): void
    {
        $courrier = Courrier::with('destinataires')->findOrFail($courrierId);
        if ($courrier->type !== 'sortant') {
            throw new \DomainException('Seul un courrier sortant peut être envoyé.');
        }
        if ($courrier->envoye_le !== null) {
            throw new \DomainException('Ce courrier a déjà été envoyé.');
        }

        $contacts = $courrier->destinataires->map(fn (CourrierDestinataire $item): array => [
            'snapshot_id' => $item->id,
            'id' => $item->destinataire_id,
            'nom' => $item->nom,
            'email' => $item->email,
            'telephone' => $item->telephone,
        ])->all();
        $resultats = $this->notifications->envoyer($contacts, $courrier->canal, $courrier->objet, (string) $courrier->contenu);

        DB::transaction(function () use ($courrier, $resultats): void {
            foreach ($resultats as $snapshotId => $notificationId) {
                CourrierDestinataire::whereKey($snapshotId)->update([
                    'statut' => $notificationId > 0 ? 'en_attente' : 'echec',
                    'notification_id' => $notificationId ?: null,
                ]);
            }
            $courrier->update(['envoye_le' => now()]);
        });
    }

    public function appliquerModele(int $modeleId, array $variables = []): array
    {
        $modele = CourrierModele::where('actif', true)->findOrFail($modeleId);
        $remplacements = [];
        foreach ($variables as $cle => $valeur) {
            $remplacements['{'.$cle.'}'] = (string) $valeur;
        }

        return ['objet' => strtr($modele->objet, $remplacements), 'contenu' => strtr($modele->contenu, $remplacements)];
    }

    public function affecterAService(int $courrierId, int $serviceId): void
    {
        $courrier = Courrier::findOrFail($courrierId);
        $courrier->update(['service_affecte_id' => $serviceId]);
        $courrier->changerStatut('affecte');
    }

    public function getCourriersEnRetard(): Collection
    {
        return Courrier::whereNotNull('date_limite_reponse')
            ->where('date_limite_reponse', '<', now())
            ->whereNotIn('statut', ['repondu', 'archive'])
            ->get();
    }

    private function resoudreContacts(string $cible, array $config): array
    {
        if (in_array($cible, ['parents_selectionnes', 'tous_parents', 'niveau', 'classe', 'classes', 'sous_niveaux'], true)) {
            return $this->destinatairesScolarite->resoudreParents($cible, $config);
        }

        $users = match ($cible) {
            'groupe' => Groupe::findOrFail((int) ($config['groupe_id'] ?? 0))->membres(),
            'tous_enseignants' => User::role('Enseignant'),
            'tout_personnel' => User::whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Parent', 'Eleve'])),
            default => User::whereKey($config['user_ids'] ?? []),
        };

        return $users->where('statut', 'actif')->get()->map(fn (User $user): array => [
            'type' => 'utilisateur',
            'id' => $user->id,
            'nom' => $user->name,
            'email' => $user->email,
            'telephone' => $user->telephone,
        ])->all();
    }
}
