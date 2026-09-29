<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus_cantine', function (Blueprint $table) {
            $table->id();
            $table->date('date_menu')->unique();
            $table->text('description');
            $table->string('allergenes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus_cantine');
    }
};
