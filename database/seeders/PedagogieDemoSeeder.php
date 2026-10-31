<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PedagogieDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $anneeId = (int) DB::table('annees_scolaires')->where('statut', 'active')->value('id');
            if (! $anneeId) {
                throw new \RuntimeException('Créez d’abord une année scolaire active.');
            }
            $categorieId = DB::table('categories_matieres')->where('code', 'GENERAL')->value('id') ?: DB::table('categories_matieres')->insertGetId(['nom' => 'Enseignement général', 'code' => 'GENERAL', 'ordre_affichage' => 1, 'actif' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $matieres = collect(['FR-DEMO' => 'Français', 'MATH-DEMO' => 'Mathématiques', 'SCI-DEMO' => 'Sciences'])->mapWithKeys(function ($nom, $code) use ($categorieId) {
                DB::table('matieres')->updateOrInsert(['code' => $code], ['nom' => $nom, 'categorie_matiere_id' => $categorieId, 'coefficient' => 1, 'coefficient_defaut' => 1, 'actif' => 1, 'updated_at' => now(), 'created_at' => now()]);

                return [$code => (int) DB::table('matieres')->where('code', $code)->value('id')];
            });
            $enseignants = collect([
                ['secondaire.demo@ambassadors.local', 'Secondaire', 'Demo', 'ENS-SEC-DEMO', 'Mathématiques', 'vacataire', 18],
                ['primaire.demo@ambassadors.local', 'Primaire', 'Demo', 'ENS-PRI-DEMO', 'Enseignement général', 'titulaire', 40],
                ['maternelle.demo@ambassadors.local', 'Maternelle', 'Demo', 'ENS-MAT-DEMO', 'Petite enfance', 'titulaire', 40],
            ])->mapWithKeys(function ($d) {
                $u = User::updateOrCreate(['email' => $d[0]], ['name' => $d[1].' '.$d[2], 'nom' => $d[1], 'prenom' => $d[2], 'password' => Hash::make('Test@2026'), 'statut' => 'actif', 'must_change_password' => false]);
                $u->syncRoles(['Enseignant']);
                DB::table('employes')->updateOrInsert(['matricule' => $d[3]], ['user_id' => $u->id, 'nom' => $d[1], 'prenom' => $d[2], 'email' => $d[0], 'poste' => 'Enseignant', 'date_embauche' => '2026-09-01', 'statut' => 'actif', 'updated_at' => now(), 'created_at' => now()]);
                $employeId = (int) DB::table('employes')->where('matricule', $d[3])->value('id');
                DB::table('enseignants')->updateOrInsert(['employe_id' => $employeId], ['specialite' => $d[4], 'statut_contractuel' => $d[5], 'charge_horaire_hebdo' => $d[6], 'updated_at' => now(), 'created_at' => now()]);

                return [$d[3] => (object) ['user' => $u->id, 'employe' => $employeId, 'enseignant' => (int) DB::table('enseignants')->where('employe_id', $employeId)->value('id')]];
            });
            $assistant = User::updateOrCreate(['email' => 'assistant.demo@ambassadors.local'], ['name' => 'Assistant Demo', 'password' => Hash::make('Test@2026'), 'statut' => 'actif', 'must_change_password' => false]);
            DB::table('employes')->updateOrInsert(['matricule' => 'ASS-DEMO'], ['user_id' => $assistant->id, 'nom' => 'Assistant', 'prenom' => 'Demo', 'email' => $assistant->email, 'poste' => 'Assistant pédagogique', 'date_embauche' => '2026-09-01', 'statut' => 'actif', 'updated_at' => now(), 'created_at' => now()]);
            $assistantId = (int) DB::table('employes')->where('matricule', 'ASS-DEMO')->value('id');
            $cp = (int) DB::table('classes')->where('code', 'CP-FR')->where('annee_scolaire_id', $anneeId)->value('id');
            $mat = (int) DB::table('classes')->where('code', 'MAT-FR')->where('annee_scolaire_id', $anneeId)->value('id');
            $six = (int) DB::table('classes')->where('code', '6E-A')->where('annee_scolaire_id', $anneeId)->value('id');
            $cinq = (int) DB::table('classes')->where('code', '5E-A')->where('annee_scolaire_id', $anneeId)->value('id');
            foreach ([[$cp, $enseignants['ENS-PRI-DEMO']], [$mat, $enseignants['ENS-MAT-DEMO']]] as [$classe,$ens]) {
                if (! $classe) {
                    continue;
                } DB::table('classes')->where('id', $classe)->update(['professeur_principal_id' => $ens->enseignant]);
                DB::table('classe_assistants')->updateOrInsert(['classe_id' => $classe, 'employe_id' => $assistantId]);
                foreach ($matieres as $matiereId) {
                    DB::table('affectations_pedagogiques')->updateOrInsert(['enseignant_id' => $ens->enseignant, 'matiere_id' => $matiereId, 'classe_id' => $classe, 'annee_scolaire_id' => $anneeId], ['actif' => 1, 'updated_at' => now(), 'created_at' => now()]);
                }
            }
            foreach (array_filter([$six, $cinq]) as $classe) {
                $classeConfiguration = DB::table('classes')->where('id', $classe)->first(['niveau_id', 'filiere_id']);
                DB::table('offres_pedagogiques')->updateOrInsert(
                    ['matiere_id' => $matieres['MATH-DEMO'], 'classe_id' => $classe, 'annee_scolaire_id' => $anneeId],
                    ['niveau_id' => $classeConfiguration->niveau_id, 'filiere_id' => $classeConfiguration->filiere_id, 'programme_id' => null, 'coefficient_matiere' => 4, 'volume_horaire' => 120, 'heures_hebdomadaires' => 5, 'actif' => 1, 'updated_at' => now(), 'created_at' => now()],
                );
                $offreId = (int) DB::table('offres_pedagogiques')
                    ->where('matiere_id', $matieres['MATH-DEMO'])
                    ->where('classe_id', $classe)
                    ->where('annee_scolaire_id', $anneeId)
                    ->value('id');
                DB::table('affectations_pedagogiques')->updateOrInsert(
                    ['enseignant_id' => $enseignants['ENS-SEC-DEMO']->enseignant, 'matiere_id' => $matieres['MATH-DEMO'], 'classe_id' => $classe, 'annee_scolaire_id' => $anneeId],
                    ['offre_pedagogique_id' => $offreId, 'coefficient' => 4, 'actif' => 1, 'updated_at' => now(), 'created_at' => now()],
                );
            }
            foreach ([[$cp, $enseignants['ENS-PRI-DEMO']->enseignant, $matieres['FR-DEMO']], [$mat, $enseignants['ENS-MAT-DEMO']->enseignant, $matieres['FR-DEMO']], [$six, $enseignants['ENS-SEC-DEMO']->enseignant, $matieres['MATH-DEMO']]] as [$classe,$ens,$matiere]) {
                if (! $classe) {
                    continue;
                } DB::table('programmes')->updateOrInsert(['classe_id' => $classe, 'matiere_id' => $matiere, 'annee_scolaire_id' => $anneeId, 'enseignant_id' => $ens], ['niveau_id' => DB::table('classes')->where('id', $classe)->value('niveau_id'), 'source' => 'enseignant', 'titre' => 'Programme général de démonstration', 'statut' => 'valide', 'updated_at' => now(), 'created_at' => now()]);
                $programmeId = (int) DB::table('programmes')->where('classe_id', $classe)->where('matiere_id', $matiere)->where('annee_scolaire_id', $anneeId)->where('enseignant_id', $ens)->value('id');
                foreach ([1 => 'Fondamentaux', 2 => 'Approfondissement', 3 => 'Mise en pratique'] as $ordre => $titre) {
                    DB::table('programme_chapitres')->updateOrInsert(['programme_id' => $programmeId, 'ordre' => $ordre], ['titre' => $titre, 'contenu' => "Partie A du chapitre {$ordre}\nPartie B du chapitre {$ordre}", 'volume_horaire_prevu' => 4, 'statut' => 'a_faire', 'updated_at' => now(), 'created_at' => now()]);
                }
            }
        });
        $this->command?->info('Données pédagogiques de démonstration créées. Comptes: secondaire.demo, primaire.demo, maternelle.demo — mot de passe Test@2026');
    }
}
