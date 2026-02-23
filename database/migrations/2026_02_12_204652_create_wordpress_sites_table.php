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
        Schema::create('wordpress_sites', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('site_name', 255);
            $table->string('site_url', 500)->unique();
            $table->unsignedSmallInteger('domain_id')->nullable()->comment('Si es uno de nuestros dominios');

            // Credenciales WordPress REST API
            $table->string('wp_rest_api_url', 500);
            $table->string('wp_username', 100)->nullable();
            $table->text('wp_app_password')->nullable()->comment('Encrypted');

            // Configuración
            $table->unsignedInteger('default_category_id')->nullable();
            $table->unsignedInteger('default_author_id')->nullable();
            $table->boolean('auto_publish')->default(false);
            $table->boolean('require_review')->default(true);

            // Estado
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_published_at')->nullable();
            $table->unsignedInteger('total_posts_published')->default(0);
            $table->timestamps();

            // Índices
            $table->index('domain_id');
            $table->index('is_active');

            // Foreign Keys
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wordpress_sites');
    }
};
