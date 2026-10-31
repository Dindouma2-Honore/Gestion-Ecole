<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_grants', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 30);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('feature_id')
                ->constrained('catalogue_fonctionnalites')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->boolean('can_read')->default(false);
            $table->boolean('can_write')->default(false);
            $table->boolean('can_execute')->default(false);
            $table->boolean('can_print')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'feature_id'], 'authorization_subject_feature_unique');
            $table->index(['subject_type', 'subject_id', 'is_active'], 'authorization_subject_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_grants');
    }
};
