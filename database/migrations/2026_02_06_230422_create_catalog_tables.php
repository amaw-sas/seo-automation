<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabla: cities - Ciudades colombianas
        Schema::create('cities', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 100)->unique();
            $table->string('department', 100);
            $table->string('region', 50);
            $table->unsignedMediumInteger('population')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tabla: categories - Categorías de keywords
        Schema::create('categories', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Tabla: search_intents - Intenciones de búsqueda
        Schema::create('search_intents', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Tabla: domain_types - Tipos de dominio
        Schema::create('domain_types', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Tabla: link_types - Tipos de enlaces
        Schema::create('link_types', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Tabla: gap_types - Tipos de gaps
        Schema::create('gap_types', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gap_types');
        Schema::dropIfExists('link_types');
        Schema::dropIfExists('domain_types');
        Schema::dropIfExists('search_intents');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('cities');
    }
};
