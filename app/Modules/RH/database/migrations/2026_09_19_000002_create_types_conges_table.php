<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_conges', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        DB::table('types_conges')->insert([
            ['code' => 'conge_annuel', 'nom' => 'Congé annuel', 'actif' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'permission_exceptionnelle', 'nom' => 'Permission exceptionnelle', 'actif' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'conge_maladie', 'nom' => 'Congé maladie', 'actif' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'conge_maternite', 'nom' => 'Congé maternité / paternité', 'actif' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'autre', 'nom' => 'Autre congé', 'actif' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('conges', function (Blueprint $table): void {
            $table->string('type', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('conges', function (Blueprint $table): void {
            $table->enum('type', ['conge_annuel', 'permission_exceptionnelle', 'conge_maladie', 'conge_maternite', 'autre'])->change();
        });
        Schema::dropIfExists('types_conges');
    }
};
