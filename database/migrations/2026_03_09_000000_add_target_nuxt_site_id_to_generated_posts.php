<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_posts', function (Blueprint $table) {
            $table->foreignId('target_nuxt_site_id')
                ->nullable()
                ->constrained('nuxt_sites')
                ->nullOnDelete()
                ->after('target_wordpress_site_id');
        });
    }

    public function down(): void
    {
        Schema::table('generated_posts', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\NuxtSite::class, 'target_nuxt_site_id');
            $table->dropColumn('target_nuxt_site_id');
        });
    }
};
