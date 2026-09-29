<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Services;

use App\Modules\Logistique\Contracts\CantineServiceInterface;
use App\Modules\Logistique\Models\AbonnementCantine;
use App\Modules\Logistique\Models\MenuCantine;
use App\Modules\Logistique\Models\PresenceCantine;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\VieScolaire\Contracts\SanteServiceInterface;

class CantineService implements CantineServiceInterface
{
    public function __construct(
        private readonly SanteServiceInterface $sante,
        private readonly AnneeScolaireServiceContract $anneeScolaire,
    ) {}

    public function souscrireAbonnement(int $eleveId, string $type, \DateTimeInterface $dateDebut): object
    {
        $dateDebutCarbon = \Illuminate\Support\Carbon::instance($dateDebut);

        $dateFin = match ($type) {
            'mensuel' => $dateDebutCarbon->copy()->addMonth(),
            'trimestriel' => $dateDebutCarbon->copy()->addMonths(3),
            'annuel' => $dateDebutCarbon->copy()->addYear(),
        };

        return AbonnementCantine::create([
            'eleve_id' => $eleveId,
            'annee_scolaire_id' => $this->anneeScolaire->getAnneeCouranteId(),
            'type' => $type,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'montant' => 0, // à définir selon grille tarifaire, potentiellement liée au module Finances (E.39)
            'statut' => 'actif',
        ]);
    }

    public function enregistrerPresenceRepas(int $abonnementId, \DateTimeInterface $date, bool $present): void
    {
        PresenceCantine::updateOrCreate(
            ['abonnement_id' => $abonnementId, 'date_repas' => $date],
            ['present' => $present]
        );
    }

    public function verifierCompatibiliteMenu(int $eleveId, \DateTimeInterface $date): array
    {
        $menu = MenuCantine::where('date_menu', $date->format('Y-m-d'))->first();
        if (! $menu || ! $menu->allergenes) {
            return ['compatible' => true, 'alertes' => []];
        }

        // Réutilise le raccourci allégé de SanteServiceInterface (module
        // VieScolaire) — pas d'accès au dossier santé complet ici, juste
        // les infos d'urgence (allergies). Volontairement getInfosUrgence()
        // et non consulterDossier(), pour ne pas déclencher un audit de
        // consultation du dossier médical complet à chaque vérification.
        $infosUrgence = $this->sante->getInfosUrgence($eleveId);

        if (! $infosUrgence->allergies) {
            return ['compatible' => true, 'alertes' => []];
        }

        $allergiesEleve = array_map('trim', explode(',', strtolower($infosUrgence->allergies)));
        $allergenesMenu = array_map('trim', explode(',', strtolower($menu->allergenes)));

        $conflits = array_intersect($allergiesEleve, $allergenesMenu);

        return [
            'compatible' => empty($conflits),
            'alertes' => array_values($conflits),
        ];
    }

    public function getEffectifPrevu(\DateTimeInterface $date): int
    {
        return AbonnementCantine::where('statut', 'actif')
            ->where('date_debut', '<=', $date)
            ->where('date_fin', '>=', $date)
            ->count();
    }
}
