<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Socle\Models\FormatNumerotation;
use Illuminate\Database\Seeder;

class DocumentNumberingSeeder extends Seeder
{
    public function run(): void
    {
        FormatNumerotation::query()->firstOrCreate(
            ['type_document' => 'recu'],
            [
                'libelle' => 'Reçu de paiement',
                'format' => 'REC-{ANNEE_SCOLAIRE}-{SEQ:5}',
                'reinitialisation' => 'annee_scolaire',
                'prochain_numero' => 1,
                'a_valider' => false,
            ],
        );
    }
}
