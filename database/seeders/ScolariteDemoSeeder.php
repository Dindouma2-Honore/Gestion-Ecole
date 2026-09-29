<?php

namespace Database\Seeders;

use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Contracts\ParentTuteurServiceInterface;
use App\Modules\Scolarite\Models\Classe;
use Illuminate\Database\Seeder;

class ScolariteDemoSeeder extends Seeder
{
    public function run(): void
    {
        $eleveService = app(EleveServiceInterface::class);
        $parentService = app(ParentTuteurServiceInterface::class);
        $inscriptionService = app(InscriptionServiceInterface::class);

        // 1. Élève
        $eleveId = $eleveService->creer([
            'nom' => 'Doe',
            'prenom' => 'Jane',
        ])['id'];

        // 2. Parent / tuteur
        $parentId = $parentService->creer([
            'nom' => 'Doe',
            'prenom' => 'Paul',
            'telephone' => '699000000',
            'email' => 'paul.doe@example.com',
            'profession' => 'Commerçant',
        ])['id'];

        $parentService->lierAEleve($parentId, $eleveId, [
            'lien_parente' => 'pere',
            'payeur' => true,
            'autorise_recuperation' => true,
        ]);

        // 3. Classe (idempotent : rejouable sans planter si elle existe déjà)
        $classe = Classe::firstOrCreate(
            ['nom' => 'CP1 A', 'annee_scolaire_id' => 1],
            ['niveau_id' => 1, 'capacite_max' => 30],
        );

        // 4. Inscription complète (élève + parent existants, classe, année)
        $inscriptionId = $inscriptionService->preparerEtDemarrerPreinscription(
            eleveId: $eleveId,
            eleve: [],
            parentId: $parentId,
            parent: [],
            lienParente: 'pere',
            classeId: $classe->id,
            anneeScolaireId: 1,
            fraisOptionnels: [],
        );

        $this->command->info("Démo Scolarité créée : élève #{$eleveId}, parent #{$parentId}, classe #{$classe->id}, inscription #{$inscriptionId}.");
    }
}