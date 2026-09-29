<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            Schema::create('activity_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('log_name')->nullable();
                $table->text('description');
                $table->nullableMorphs('subject', 'subject');
                $table->nullableMorphs('causer', 'causer');
                $table->json('properties')->nullable();
                $table->uuid('batch_uuid')->nullable();
                $table->string('event')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('motif')->nullable();
                $table->timestamps();

                $table->index('log_name');
            });
        } else {
            Schema::table('activity_log', function (Blueprint $table) {
                if (! Schema::hasColumn('activity_log', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable();
                }
                if (! Schema::hasColumn('activity_log', 'motif')) {
                    $table->text('motif')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
