<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parents_tuteurs')) {
            return;
        }

        $allParents = DB::table('parents_tuteurs')->get();

        $groupes = $allParents->groupBy(function ($parent): string {
            $nom = trim((string) $parent->nom);
            return mb_strtolower($nom);
        });

        $placeholders = [
            'tuteur à compléter',
            'tuteur a completer',
            'tuteur',
            'parent',
            'inconnu',
            '',
        ];

        DB::transaction(function () use ($groupes, $placeholders) {
            foreach ($groupes as $nomNormalise => $parents) {
                if ($parents->count() <= 1) {
                    continue;
                }

                if (in_array($nomNormalise, $placeholders, true) || str_starts_with($nomNormalise, 'tuteur de ')) {
                    continue;
                }

                // Trouver le parent canonique (avec le plus d'élèves, téléphone, puis plus petit id)
                $parentsWithCount = $parents->map(function ($p) {
                    $enfantsCount = DB::table('eleve_parent')->where('parent_id', $p->id)->count();
                    $hasPhone = ! empty(trim((string) $p->telephone)) ? 1 : 0;
                    $hasEmail = ! empty(trim((string) $p->email)) ? 1 : 0;

                    return [
                        'record' => $p,
                        'enfants' => $enfantsCount,
                        'contact' => $hasPhone + $hasEmail,
                        'id' => (int) $p->id,
                    ];
                })->sort(function ($a, $b) {
                    if ($a['enfants'] !== $b['enfants']) {
                        return $b['enfants'] <=> $a['enfants'];
                    }
                    if ($a['contact'] !== $b['contact']) {
                        return $b['contact'] <=> $a['contact'];
                    }
                    return $a['id'] <=> $b['id'];
                });

                $canonical = $parentsWithCount->first()['record'];
                $duplicates = $parentsWithCount->slice(1)->pluck('record');

                foreach ($duplicates as $duplicate) {
                    // Enrichir le canonique si des champs sont manquants
                    $updates = [];
                    if (empty(trim((string) $canonical->telephone)) && ! empty(trim((string) $duplicate->telephone))) {
                        $updates['telephone'] = trim((string) $duplicate->telephone);
                        $canonical->telephone = $updates['telephone'];
                    }
                    if (empty(trim((string) $canonical->email)) && ! empty(trim((string) $duplicate->email))) {
                        $updates['email'] = trim((string) $duplicate->email);
                        $canonical->email = $updates['email'];
                    }
                    if (empty(trim((string) $canonical->profession)) && ! empty(trim((string) $duplicate->profession))) {
                        $updates['profession'] = trim((string) $duplicate->profession);
                        $canonical->profession = $updates['profession'];
                    }
                    if (! empty($updates)) {
                        DB::table('parents_tuteurs')->where('id', $canonical->id)->update($updates);
                    }

                    // Migrer eleve_parent
                    $relations = DB::table('eleve_parent')->where('parent_id', $duplicate->id)->get();
                    foreach ($relations as $rel) {
                        $existingCanonicalRel = DB::table('eleve_parent')
                            ->where('parent_id', $canonical->id)
                            ->where('eleve_id', $rel->eleve_id)
                            ->first();

                        if (! $existingCanonicalRel) {
                            DB::table('eleve_parent')
                                ->where('parent_id', $duplicate->id)
                                ->where('eleve_id', $rel->eleve_id)
                                ->update(['parent_id' => $canonical->id]);
                        } else {
                            // L'élève est déjà lié au canonique, supprimer le doublon de pivot
                            DB::table('eleve_parent')
                                ->where('parent_id', $duplicate->id)
                                ->where('eleve_id', $rel->eleve_id)
                                ->delete();
                        }
                    }

                    // Migrer les tables associées
                    $tablesToMigrate = [
                        'factures',
                        'factures_generees',
                        'factures_preinscription',
                        'message_destinataires',
                        'rendez_vous',
                        'reponses_parents',
                        'sorties_eleves',
                    ];

                    foreach ($tablesToMigrate as $table) {
                        if (Schema::hasTable($table) && Schema::hasColumn($table, 'parent_id')) {
                            DB::table($table)->where('parent_id', $duplicate->id)->update(['parent_id' => $canonical->id]);
                        }
                    }

                    // Supprimer le parent doublon
                    DB::table('parents_tuteurs')->where('id', $duplicate->id)->delete();
                }
            }
        });
    }

    public function down(): void
    {
        // Fusion irréversible sans perte de rattachement des élèves.
    }
};
