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
        Schema::create('generated_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('content_strategy_id')->nullable();
            $table->unsignedMediumInteger('topic_research_id')->nullable();

            // Contenido
            $table->string('title', 500);
            $table->string('slug', 500);
            $table->longText('content')->comment('HTML content');
            $table->text('excerpt')->nullable();
            $table->string('meta_description', 160)->nullable();

            // Keywords objetivo
            $table->unsignedInteger('primary_keyword_id');
            $table->json('secondary_keywords')->nullable()->comment('Array de keyword_ids');

            // LLM usado
            $table->string('llm_provider', 50)->comment('anthropic, openai, google, xai');
            $table->string('llm_model', 100)->comment('claude-3-5-sonnet, gpt-4, etc.');
            $table->unsignedInteger('llm_prompt_tokens')->nullable();
            $table->unsignedInteger('llm_completion_tokens')->nullable();
            $table->decimal('llm_cost_usd', 8, 4)->nullable();

            // Imágenes
            $table->text('featured_image_url')->nullable();
            $table->json('inline_images')->nullable()->comment('Array de URLs');
            $table->string('image_llm_provider', 50)->nullable()->comment('dalle3, midjourney, stable-diffusion, xai');
            $table->decimal('image_generation_cost_usd', 8, 4)->nullable();

            // Estado
            $table->enum('status', ['draft', 'pending_review', 'approved', 'published', 'rejected'])->default('draft');
            $table->unsignedTinyInteger('quality_score')->nullable()->comment('0-100, calculado automáticamente');

            // Publicación
            $table->unsignedSmallInteger('target_wordpress_site_id')->nullable();
            $table->unsignedInteger('wordpress_post_id')->nullable();
            $table->text('published_url')->nullable();
            $table->timestamp('published_at')->nullable();

            // Metadata
            $table->unsignedInteger('word_count')->nullable();
            $table->unsignedTinyInteger('reading_time_minutes')->nullable();
            $table->unsignedTinyInteger('seo_score')->nullable()->comment('0-100');
            $table->timestamps();

            // Índices
            $table->index('content_strategy_id');
            $table->index('topic_research_id');
            $table->index('primary_keyword_id');
            $table->index('status');
            $table->index('published_at');
            $table->index('target_wordpress_site_id');

            // Foreign Keys
            $table->foreign('content_strategy_id')->references('id')->on('content_strategies')->onDelete('set null');
            $table->foreign('topic_research_id')->references('id')->on('topic_research')->onDelete('set null');
            $table->foreign('primary_keyword_id')->references('id')->on('keywords')->onDelete('cascade');
            $table->foreign('target_wordpress_site_id')->references('id')->on('wordpress_sites')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_posts');
    }
};
