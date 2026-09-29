<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Numérotation via Socle (ParametrageServiceContract::genererNumero() /
 * NumerotationServiceContract) : les anciens compteurs locaux
 * (compteur_matricules, compteurs_factures, compteurs_recus) sont supprimés
 * — voir 2026_09_01_000012 et 2026_09_01_000011 — au profit de la table
 * `formats_numerotation` de Socle, désormais confirmée (contrat et
 * implémentation lus intégralement).
 *
 * Socle pré-sème déjà 'facture' (FACT-{ANNEE_SCOLAIRE}-{SEQ:5}, voir
 * 2026_08_21_000001_make_numbering_formats_configurable côté Socle) — un
 * type manifestement prévu pour ce module (aux côtés de 'bulletin' et
 * 'carte_scolaire', tous deux du domaine Scolarité) : la facture définitive
 * le réutilise tel quel, sans le re-semer ici.
 *
 * Trois types restent propres à Scolarité et n'existent pas côté Socle :
 * - matricule_eleve : identifiant permanent de l'élève, attribué à la
 *   création du dossier (Eleve::booted) — remis à zéro chaque année
 *   scolaire, comme le faisait l'ancien compteur.
 * - facture_provisoire_scolarite : la provisoire n'est pas un document
 *   fiscal validé (a_valider = false), contrairement à la définitive — on
 *   ne réutilise donc pas le type 'facture' de Socle pour elle.
 * - recu_versement : reçu remis à l'encaissement d'un versement.
 */
return new class extends Migration
{
    public function up(): void
    {
        $maintenant = now();

        DB::table('formats_numerotation')->insertOrIgnore([
            [
                'type_document' => 'matricule_eleve',
                'libelle' => 'Matricule élève',
                'format' => 'AMB-{ANNEE_SCOLAIRE}-{SEQ:6}',
                'reinitialisation' => 'annee_scolaire',
                'prochain_numero' => 1,
                'a_valider' => false,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ],
            [
                'type_document' => 'facture_provisoire_scolarite',
                'libelle' => 'Facture provisoire (scolarité)',
                'format' => 'FACT-PROV-{ANNEE_SCOLAIRE}-{SEQ:5}',
                'reinitialisation' => 'annee_scolaire',
                'prochain_numero' => 1,
                'a_valider' => false,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ],
            [
                'type_document' => 'recu_versement',
                'libelle' => 'Reçu de versement',
                'format' => 'REC-{ANNEE_SCOLAIRE}-{SEQ:6}',
                'reinitialisation' => 'annee_scolaire',
                'prochain_numero' => 1,
                'a_valider' => false,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('formats_numerotation')
            ->whereIn('type_document', ['matricule_eleve', 'facture_provisoire_scolarite', 'recu_versement'])
            ->delete();
    }
};
