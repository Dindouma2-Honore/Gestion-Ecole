<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycles', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 100);
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
        Schema::create('filieres', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 100);
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
        Schema::create('sections_scolaires', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 100);
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
        Schema::table('niveaux', function (Blueprint $table): void {
            $table->foreignId('cycle_id')->nullable()->after('id')->constrained('cycles')->nullOnDelete();
            $table->index('cycle_id');
        });
    }

    public function down(): void
    {
        Schema::table('niveaux', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cycle_id');
        });
        Schema::dropIfExists('sections_scolaires');
        Schema::dropIfExists('filieres');
        Schema::dropIfExists('cycles');
    }
};
