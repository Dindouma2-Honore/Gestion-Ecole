<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', fn (Blueprint $t) => $t->index(['subject_type', 'subject_id', 'created_at'], 'audit_trail_subject_index'));
    }

    public function down(): void {}
};
