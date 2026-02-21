<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Índices compuestos para keywords
        DB::statement('CREATE INDEX idx_keywords_city_volume ON keywords(city_id, search_volume_co DESC)');
        DB::statement('CREATE INDEX idx_keywords_opportunity ON keywords(keyword_difficulty ASC, search_volume_co DESC)');
        DB::statement('CREATE INDEX idx_keywords_intent_volume ON keywords(intent_id, search_volume_co DESC)');

        // Índices compuestos para keyword_rankings
        DB::statement('CREATE INDEX idx_rankings_keyword_date ON keyword_rankings(keyword_id, snapshot_date DESC)');
        DB::statement('CREATE INDEX idx_rankings_domain_date ON keyword_rankings(domain_id, snapshot_date DESC)');
        DB::statement('CREATE INDEX idx_rankings_position ON keyword_rankings(position ASC, snapshot_date DESC)');
        DB::statement('CREATE INDEX idx_rankings_month ON keyword_rankings(snapshot_month)');

        // Índices compuestos para backlinks
        DB::statement('CREATE INDEX idx_backlinks_target_active ON backlinks(target_domain_id, is_active, quality_score DESC)');
        DB::statement('CREATE INDEX idx_backlinks_referring ON backlinks(referring_domain_id, is_active)');
        DB::statement('CREATE INDEX idx_backlinks_quality ON backlinks(quality_score DESC, is_spam)');

        // Índices compuestos para keyword_gaps
        DB::statement('CREATE INDEX idx_gaps_domain_type_score ON keyword_gaps(our_domain_id, gap_type_id, opportunity_score DESC)');
        DB::statement('CREATE INDEX idx_gaps_keyword ON keyword_gaps(keyword_id, our_domain_id)');
        DB::statement('CREATE INDEX idx_gaps_date ON keyword_gaps(analysis_date DESC)');

        // Índices compuestos para backlink_opportunities
        DB::statement('CREATE INDEX idx_backlink_opp_priority ON backlink_opportunities(our_domain_id, priority, status)');
        DB::statement('CREATE INDEX idx_backlink_opp_status ON backlink_opportunities(status, identified_at DESC)');

        // Índices para referring_domains
        DB::statement('CREATE INDEX idx_referring_as ON referring_domains(authority_score DESC, is_spam)');
        DB::statement('CREATE INDEX idx_referring_category ON referring_domains(category, authority_score DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop índices en orden inverso
        Schema::table('referring_domains', function (Blueprint $table) {
            $table->dropIndex('idx_referring_category');
            $table->dropIndex('idx_referring_as');
        });

        Schema::table('backlink_opportunities', function (Blueprint $table) {
            $table->dropIndex('idx_backlink_opp_status');
            $table->dropIndex('idx_backlink_opp_priority');
        });

        Schema::table('keyword_gaps', function (Blueprint $table) {
            $table->dropIndex('idx_gaps_date');
            $table->dropIndex('idx_gaps_keyword');
            $table->dropIndex('idx_gaps_domain_type_score');
        });

        Schema::table('backlinks', function (Blueprint $table) {
            $table->dropIndex('idx_backlinks_quality');
            $table->dropIndex('idx_backlinks_referring');
            $table->dropIndex('idx_backlinks_target_active');
        });

        Schema::table('keyword_rankings', function (Blueprint $table) {
            $table->dropIndex('idx_rankings_month');
            $table->dropIndex('idx_rankings_position');
            $table->dropIndex('idx_rankings_domain_date');
            $table->dropIndex('idx_rankings_keyword_date');
        });

        Schema::table('keywords', function (Blueprint $table) {
            $table->dropIndex('idx_keywords_intent_volume');
            $table->dropIndex('idx_keywords_opportunity');
            $table->dropIndex('idx_keywords_city_volume');
        });
    }
};
