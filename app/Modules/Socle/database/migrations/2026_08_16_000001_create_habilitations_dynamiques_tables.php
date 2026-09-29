<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_fonctionnalites', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->string('categorie', 80)->index();
            $table->string('module_technique', 80)->nullable()->index();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('habilitations_roles_fonctionnalites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('fonctionnalite_id')->constrained('catalogue_fonctionnalites')->cascadeOnUpdate()->cascadeOnDelete();
            $table->boolean('actif')->default(true);
            $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('dernier_motif')->nullable();
            $table->timestamp('modifie_le')->nullable();
            $table->timestamps();

            $table->unique(['role_id', 'fonctionnalite_id'], 'habilitation_role_fonctionnalite_unique');
            $table->index(['role_id', 'actif']);
        });

        $maintenant = now();
        $catalogue = [
            ['code' => 'module.socle', 'nom' => 'Administration générale', 'categorie' => 'Administration', 'module_technique' => 'Socle', 'ordre' => 10],
            ['code' => 'module.rh', 'nom' => 'Ressources humaines', 'categorie' => 'RH', 'module_technique' => 'RH', 'ordre' => 20],
            ['code' => 'module.scolarite', 'nom' => 'Scolarité et inscriptions', 'categorie' => 'Scolarité', 'module_technique' => 'Scolarite', 'ordre' => 30],
            ['code' => 'module.pedagogie', 'nom' => 'Pédagogie', 'categorie' => 'Pédagogie', 'module_technique' => 'Pedagogie', 'ordre' => 40],
            ['code' => 'module.finances', 'nom' => 'Finances', 'categorie' => 'Finances', 'module_technique' => 'Finances', 'ordre' => 50],
            ['code' => 'module.communication', 'nom' => 'Communication', 'categorie' => 'Communication', 'module_technique' => 'Communication', 'ordre' => 60],
            ['code' => 'module.assiduite', 'nom' => 'Vie scolaire et assiduité', 'categorie' => 'Vie scolaire', 'module_technique' => 'Assiduite', 'ordre' => 70],
            ['code' => 'module.logistique', 'nom' => 'Logistique', 'categorie' => 'Logistique', 'module_technique' => 'Logistique', 'ordre' => 80],
            ['code' => 'module.rapports', 'nom' => 'Rapports', 'categorie' => 'Rapports', 'module_technique' => null, 'ordre' => 90],
        ];

        foreach ($catalogue as $item) {
            DB::table('catalogue_fonctionnalites')->insert([
                ...$item,
                'description' => null,
                'actif' => true,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }

        $roles = DB::table('roles')->whereIn('name', ['Directeur', 'Enseignant', 'Comptable', 'SurveillantGeneral', 'ChargeLogistique'])->pluck('id');
        $fonctionnalites = DB::table('catalogue_fonctionnalites')->pluck('id');
        foreach ($roles as $roleId) {
            foreach ($fonctionnalites as $fonctionnaliteId) {
                DB::table('habilitations_roles_fonctionnalites')->insert([
                    'role_id' => $roleId,
                    'fonctionnalite_id' => $fonctionnaliteId,
                    'actif' => true,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('habilitations_roles_fonctionnalites');
        Schema::dropIfExists('catalogue_fonctionnalites');
    }
};
