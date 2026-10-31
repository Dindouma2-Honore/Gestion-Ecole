<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', fn (Blueprint $t) => $t->string('sha256', 64)->nullable());
        Schema::table('document_versions', fn (Blueprint $t) => $t->string('sha256', 64)->nullable());
    }

    public function down(): void {}
};
