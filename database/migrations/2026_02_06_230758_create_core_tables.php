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
        // Tabla: domains - Dominios analizados
        Schema::create('domains', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('domain', 255)->unique();
            $table->unsignedTinyInteger('domain_type_id');
            $table->boolean('is_own')->default(false);
            $table->unsignedTinyInteger('authority_score')->nullable();
            $table->unsignedInteger('total_backlinks')->default(0);
            $table->unsignedMediumInteger('referring_domains')->default(0);
            $table->unsignedInteger('organic_traffic')->nullable();
            $table->unsignedInteger('organic_keywords')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('domain_type_id')->references('id')->on('domain_types')->onDelete('restrict');
        });

        // Tabla: keywords - Keywords con métricas SEMRush
        Schema::create('keywords', function (Blueprint $table) {
            $table->unsignedInteger('id', true);
            $table->string('keyword', 500);
            $table->string('keyword_normalized', 500)->index();
            $table->unsignedTinyInteger('city_id')->nullable();
            $table->unsignedTinyInteger('category_id')->nullable();
            $table->unsignedTinyInteger('intent_id')->nullable();
            $table->unsignedMediumInteger('search_volume_co')->default(0);
            $table->unsignedMediumInteger('search_volume_global')->nullable();
            $table->decimal('keyword_difficulty', 5, 2)->nullable();
            $table->decimal('cpc_usd', 8, 2)->nullable();
            $table->json('serp_features')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('intent_id')->references('id')->on('search_intents')->onDelete('set null');

            $table->unique(['keyword_normalized', 'city_id']);
        });

        // Tabla: keyword_rankings - Posiciones históricas
        Schema::create('keyword_rankings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('keyword_id');
            $table->unsignedSmallInteger('domain_id');
            $table->unsignedTinyInteger('position');
            $table->string('url', 1000)->nullable();
            $table->unsignedInteger('estimated_traffic')->nullable();
            $table->date('snapshot_date');
            $table->string('snapshot_month', 7)->index();
            $table->timestamps();

            $table->foreign('keyword_id')->references('id')->on('keywords')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');

            $table->unique(['keyword_id', 'domain_id', 'snapshot_date']);
        });

        // Tabla: referring_domains - Dominios que enlazan
        Schema::create('referring_domains', function (Blueprint $table) {
            $table->unsignedMediumInteger('id', true);
            $table->string('domain', 255)->unique();
            $table->unsignedTinyInteger('authority_score')->nullable();
            $table->string('category', 100)->nullable();
            $table->unsignedMediumInteger('total_backlinks')->default(0);
            $table->boolean('is_spam')->default(false);
            $table->timestamps();
        });

        // Tabla: backlinks - Enlaces entrantes
        Schema::create('backlinks', function (Blueprint $table) {
            $table->unsignedInteger('id', true);
            $table->unsignedMediumInteger('referring_domain_id');
            $table->unsignedSmallInteger('target_domain_id');
            $table->string('source_url', 1000);
            $table->string('target_url', 1000);
            $table->text('anchor_text')->nullable();
            $table->unsignedTinyInteger('link_type_id')->nullable();
            $table->date('first_seen_at')->nullable();
            $table->date('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_spam')->default(false);
            $table->unsignedTinyInteger('quality_score')->nullable();
            $table->timestamps();

            $table->foreign('referring_domain_id')->references('id')->on('referring_domains')->onDelete('cascade');
            $table->foreign('target_domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->foreign('link_type_id')->references('id')->on('link_types')->onDelete('set null');

            $table->index(['target_domain_id', 'is_active']);
        });

        // Tabla: domain_pages - Top pages por dominio
        Schema::create('domain_pages', function (Blueprint $table) {
            $table->unsignedInteger('id', true);
            $table->unsignedSmallInteger('domain_id');
            $table->string('url', 1000);
            $table->unsignedMediumInteger('traffic')->default(0);
            $table->unsignedSmallInteger('keywords_count')->default(0);
            $table->unsignedSmallInteger('backlinks_count')->default(0);
            $table->date('snapshot_date');
            $table->timestamps();

            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->unique(['domain_id', 'url', 'snapshot_date'], 'idx_domain_page_snapshot');
        });

        // Tabla: site_audits - Auditorías técnicas
        Schema::create('site_audits', function (Blueprint $table) {
            $table->unsignedMediumInteger('id', true);
            $table->unsignedSmallInteger('domain_id');
            $table->unsignedMediumInteger('pages_crawled')->default(0);
            $table->unsignedTinyInteger('site_health_score')->nullable();
            $table->unsignedSmallInteger('errors')->default(0);
            $table->unsignedSmallInteger('warnings')->default(0);
            $table->unsignedSmallInteger('notices')->default(0);
            $table->json('audit_summary')->nullable();
            $table->date('audit_date');
            $table->timestamps();

            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Tabla: site_audit_issues - Detalle de issues
        Schema::create('site_audit_issues', function (Blueprint $table) {
            $table->unsignedInteger('id', true);
            $table->unsignedMediumInteger('site_audit_id');
            $table->string('issue_type', 100);
            $table->enum('severity', ['error', 'warning', 'notice']);
            $table->text('description');
            $table->unsignedSmallInteger('affected_pages')->default(0);
            $table->text('example_url')->nullable();
            $table->timestamps();

            $table->foreign('site_audit_id')->references('id')->on('site_audits')->onDelete('cascade');
        });

        // Tabla: keyword_gaps - Análisis de brechas competitivas
        Schema::create('keyword_gaps', function (Blueprint $table) {
            $table->unsignedInteger('id', true);
            $table->unsignedInteger('keyword_id');
            $table->unsignedSmallInteger('our_domain_id');
            $table->unsignedSmallInteger('competitor_domain_id');
            $table->unsignedTinyInteger('gap_type_id');
            $table->unsignedTinyInteger('our_position')->nullable();
            $table->unsignedTinyInteger('competitor_position')->nullable();
            $table->unsignedSmallInteger('position_difference')->nullable();
            $table->unsignedTinyInteger('opportunity_score')->nullable();
            $table->date('analysis_date');
            $table->timestamps();

            $table->foreign('keyword_id')->references('id')->on('keywords')->onDelete('cascade');
            $table->foreign('our_domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->foreign('competitor_domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->foreign('gap_type_id')->references('id')->on('gap_types')->onDelete('restrict');

            $table->unique(['keyword_id', 'our_domain_id', 'competitor_domain_id', 'analysis_date'], 'idx_keyword_gap_unique');
        });

        // Tabla: backlink_opportunities - Oportunidades priorizadas
        Schema::create('backlink_opportunities', function (Blueprint $table) {
            $table->unsignedMediumInteger('id', true);
            $table->unsignedMediumInteger('referring_domain_id');
            $table->unsignedSmallInteger('competitor_domain_id');
            $table->unsignedSmallInteger('our_domain_id');
            $table->enum('opportunity_type', ['missing', 'competitor_exclusive', 'high_authority']);
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['identified', 'in_progress', 'acquired', 'rejected'])->default('identified');
            $table->text('notes')->nullable();
            $table->date('identified_at');
            $table->date('acquired_at')->nullable();
            $table->timestamps();

            $table->foreign('referring_domain_id')->references('id')->on('referring_domains')->onDelete('cascade');
            $table->foreign('competitor_domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->foreign('our_domain_id')->references('id')->on('domains')->onDelete('cascade');

            $table->unique(['referring_domain_id', 'our_domain_id'], 'idx_backlink_opp_unique');
        });

        // Tabla: topic_research - Investigación de contenido
        Schema::create('topic_research', function (Blueprint $table) {
            $table->unsignedMediumInteger('id', true);
            $table->string('title', 500);
            $table->unsignedTinyInteger('city_id')->nullable();
            $table->unsignedSmallInteger('potential_traffic')->nullable();
            $table->unsignedTinyInteger('competition_level')->nullable();
            $table->json('recommended_keywords')->nullable();
            $table->text('content_outline')->nullable();
            $table->timestamps();

            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
        });

        // Tabla: content_strategies - Planes de acción
        Schema::create('content_strategies', function (Blueprint $table) {
            $table->unsignedSmallInteger('id', true);
            $table->unsignedTinyInteger('city_id')->nullable();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->enum('phase', ['quick_wins', 'medium_term', 'long_term'])->default('medium_term');
            $table->enum('status', ['draft', 'approved', 'in_progress', 'completed'])->default('draft');
            $table->unsignedSmallInteger('target_keywords_count')->default(0);
            $table->unsignedMediumInteger('estimated_traffic')->nullable();
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->timestamps();

            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_strategies');
        Schema::dropIfExists('topic_research');
        Schema::dropIfExists('backlink_opportunities');
        Schema::dropIfExists('keyword_gaps');
        Schema::dropIfExists('site_audit_issues');
        Schema::dropIfExists('site_audits');
        Schema::dropIfExists('domain_pages');
        Schema::dropIfExists('backlinks');
        Schema::dropIfExists('referring_domains');
        Schema::dropIfExists('keyword_rankings');
        Schema::dropIfExists('keywords');
        Schema::dropIfExists('domains');
    }
};
