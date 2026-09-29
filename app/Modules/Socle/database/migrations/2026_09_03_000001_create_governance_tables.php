<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50);
            $table->string('nom');
            $table->string('type_document', 80);
            $table->string('module_proprietaire', 80);
            $table->string('cycle', 80)->nullable();
            $table->longText('contenu');
            $table->json('mise_en_page')->nullable();
            $table->string('orientation', 20)->default('portrait');
            $table->string('format_papier', 20)->default('A4');
            $table->unsignedInteger('version');
            $table->boolean('actif')->default(false);
            $table->date('date_effet');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['code', 'version']);
            $table->index(['code', 'actif']);
        });

        Schema::create('workflow_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80);
            $table->string('nom');
            $table->string('module_proprietaire', 80);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['code', 'version']);
        });

        Schema::create('workflow_etapes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ordre');
            $table->string('nom');
            $table->string('validateur_type', 40);
            $table->string('validateur_valeur')->nullable();
            $table->json('condition')->nullable();
            $table->timestamps();
            $table->unique(['workflow_definition_id', 'ordre']);
        });

        Schema::create('workflow_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('workflow_definition_version');
            $table->string('module_source', 80);
            $table->string('entite_type');
            $table->unsignedBigInteger('entite_id');
            $table->string('statut', 30)->default('en_cours');
            $table->foreignId('etape_courante_id')->nullable()->constrained('workflow_etapes')->nullOnDelete();
            $table->json('definition_snapshot');
            $table->json('contexte')->nullable();
            $table->timestamps();
            $table->index(['entite_type', 'entite_id']);
        });

        Schema::create('workflow_instance_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('etape_id')->constrained('workflow_etapes')->restrictOnDelete();
            $table->foreignId('acteur_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20);
            $table->text('motif');
            $table->timestamp('created_at')->useCurrent();
        });

        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'code' => 'VALIDATION_DEPENSE',
            'nom' => 'Validation des dépenses',
            'module_proprietaire' => 'Finances',
            'version' => 1,
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('workflow_etapes')->insert([
            'workflow_definition_id' => $definitionId,
            'ordre' => 1,
            'nom' => 'Validation du Fondateur',
            'validateur_type' => 'fondateur',
            'validateur_valeur' => null,
            'condition' => json_encode(['seuil_defaut' => 100000, 'seuils_par_categorie' => []], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('bulletins', function (Blueprint $table): void {
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->unsignedInteger('document_template_version')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->longText('rendered_html')->nullable();
        });

        Schema::table('depenses', function (Blueprint $table): void {
            $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('depenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('workflow_instance_id'));
        Schema::table('bulletins', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('document_template_id');
            $table->dropColumn(['document_template_version', 'generated_at', 'rendered_html']);
        });
        Schema::dropIfExists('workflow_instance_transitions');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_etapes');
        Schema::dropIfExists('workflow_definitions');
        Schema::dropIfExists('document_templates');
    }
};
