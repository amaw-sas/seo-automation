<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nuxt_sites', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('site_name', 255);
            $table->string('franchise', 100)->unique()->comment('Corresponde a rentacarFranchise en nuxt.config');
            $table->string('site_url', 500);
            $table->text('api_key')->nullable()->comment('Encrypted — x-api-key del endpoint wordpress-sync');
            $table->unsignedSmallInteger('domain_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedInteger('total_posts_synced')->default(0);
            $table->timestamps();

            $table->index('domain_id');
            $table->index('is_active');

            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nuxt_sites');
    }
};
