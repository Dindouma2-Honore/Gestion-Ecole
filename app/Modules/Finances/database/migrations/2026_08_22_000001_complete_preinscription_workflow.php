<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('factures_preinscription', 'document_provisoire_id')) {
            Schema::table('factures_preinscription', function (Blueprint $table): void {
                $table->unsignedBigInteger('document_provisoire_id')->nullable()->after('paiement_id');
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        if (Schema::hasColumn('factures_preinscription', 'document_provisoire_id')) {
            Schema::table('factures_preinscription', fn (Blueprint $table) => $table->dropColumn('document_provisoire_id'));
        }
    }
};
