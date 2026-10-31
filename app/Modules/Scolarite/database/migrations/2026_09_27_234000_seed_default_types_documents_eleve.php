<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $defaultTypes = [
            [
                'code' => 'ACTE_NAISSANCE',
                'nom' => 'Acte de naissance / Extrait d’acte de naissance',
                'obligatoire' => false,
                'actif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PHOTO_IDENTITE',
                'nom' => 'Photo d’identité',
                'obligatoire' => false,
                'actif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'CARNET_VACCINATION',
                'nom' => 'Carnet de vaccination / Certificat médical',
                'obligatoire' => false,
                'actif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'BULLETIN_NOTES',
                'nom' => 'Dernier bulletin de notes / Livret scolaire',
                'obligatoire' => false,
                'actif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'CERTIFICAT_RADIATION',
                'nom' => 'Certificat de scolarité ou de radiation',
                'obligatoire' => false,
                'actif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ENGAGEMENT_SIGNE',
                'nom' => 'Fiche d’engagement / Règlement intérieur signé',
                'obligatoire' => false,
                'actif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('types_documents_eleve')->insertOrIgnore($defaultTypes);
    }

    public function down(): void
    {
        DB::table('types_documents_eleve')
            ->whereIn('code', [
                'ACTE_NAISSANCE',
                'PHOTO_IDENTITE',
                'CARNET_VACCINATION',
                'BULLETIN_NOTES',
                'CERTIFICAT_RADIATION',
                'ENGAGEMENT_SIGNE',
            ])
            ->delete();
    }
};
