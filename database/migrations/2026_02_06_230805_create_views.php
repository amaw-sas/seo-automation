<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vista 1: v_keywords_full - Keywords con todas sus relaciones
        DB::statement("
            CREATE OR REPLACE VIEW v_keywords_full AS
            SELECT
                k.id,
                k.keyword,
                k.keyword_normalized,
                c.name AS city,
                cat.name AS category,
                si.name AS intent,
                k.search_volume_co,
                k.search_volume_global,
                k.keyword_difficulty,
                k.cpc_usd,
                k.serp_features,
                k.created_at
            FROM keywords k
            LEFT JOIN cities c ON k.city_id = c.id
            LEFT JOIN categories cat ON k.category_id = cat.id
            LEFT JOIN search_intents si ON k.intent_id = si.id
        ");

        // Vista 2: v_current_rankings - Rankings del último snapshot
        DB::statement("
            CREATE OR REPLACE VIEW v_current_rankings AS
            SELECT
                kr.id,
                kr.keyword_id,
                k.keyword,
                kr.domain_id,
                d.domain,
                kr.position,
                kr.url,
                kr.estimated_traffic,
                kr.snapshot_date
            FROM keyword_rankings kr
            INNER JOIN keywords k ON kr.keyword_id = k.id
            INNER JOIN domains d ON kr.domain_id = d.id
            INNER JOIN (
                SELECT keyword_id, domain_id, MAX(snapshot_date) AS max_date
                FROM keyword_rankings
                GROUP BY keyword_id, domain_id
            ) latest ON kr.keyword_id = latest.keyword_id
                    AND kr.domain_id = latest.domain_id
                    AND kr.snapshot_date = latest.max_date
        ");

        // Vista 3: v_keyword_opportunities - Gaps no atendidos ordenados por score
        DB::statement("
            CREATE OR REPLACE VIEW v_keyword_opportunities AS
            SELECT
                kg.id,
                kg.keyword_id,
                k.keyword,
                k.search_volume_co,
                k.keyword_difficulty,
                kg.our_domain_id,
                d_our.domain AS our_domain,
                kg.competitor_domain_id,
                d_comp.domain AS competitor_domain,
                gt.name AS gap_type,
                kg.competitor_position,
                kg.opportunity_score,
                kg.analysis_date
            FROM keyword_gaps kg
            INNER JOIN keywords k ON kg.keyword_id = k.id
            INNER JOIN domains d_our ON kg.our_domain_id = d_our.id
            INNER JOIN domains d_comp ON kg.competitor_domain_id = d_comp.id
            INNER JOIN gap_types gt ON kg.gap_type_id = gt.id
            WHERE kg.opportunity_score IS NOT NULL
            ORDER BY kg.opportunity_score DESC, k.search_volume_co DESC
        ");

        // Vista 4: v_quality_backlinks - Backlinks sin spam, score >= 3
        DB::statement("
            CREATE OR REPLACE VIEW v_quality_backlinks AS
            SELECT
                b.id,
                b.referring_domain_id,
                rd.domain AS referring_domain,
                rd.authority_score,
                b.target_domain_id,
                d.domain AS target_domain,
                b.source_url,
                b.target_url,
                b.anchor_text,
                lt.name AS link_type,
                b.quality_score,
                b.first_seen_at,
                b.last_seen_at,
                b.is_active
            FROM backlinks b
            INNER JOIN referring_domains rd ON b.referring_domain_id = rd.id
            INNER JOIN domains d ON b.target_domain_id = d.id
            LEFT JOIN link_types lt ON b.link_type_id = lt.id
            WHERE b.is_spam = 0
              AND (b.quality_score IS NULL OR b.quality_score >= 3)
              AND b.is_active = 1
        ");

        // Vista 5: v_domains_summary - Métricas actualizadas por dominio
        DB::statement("
            CREATE OR REPLACE VIEW v_domains_summary AS
            SELECT
                d.id,
                d.domain,
                dt.name AS domain_type,
                d.is_own,
                d.authority_score,
                d.total_backlinks,
                d.referring_domains,
                d.organic_traffic,
                d.organic_keywords,
                COUNT(DISTINCT kr.keyword_id) AS current_ranking_keywords,
                AVG(kr.position) AS avg_position,
                COUNT(DISTINCT b.id) AS active_backlinks_count,
                AVG(rd.authority_score) AS avg_referring_as
            FROM domains d
            INNER JOIN domain_types dt ON d.domain_type_id = dt.id
            LEFT JOIN keyword_rankings kr ON d.id = kr.domain_id
                AND kr.snapshot_date = (SELECT MAX(snapshot_date) FROM keyword_rankings WHERE domain_id = d.id)
            LEFT JOIN backlinks b ON d.id = b.target_domain_id
                AND b.is_active = 1 AND b.is_spam = 0
            LEFT JOIN referring_domains rd ON b.referring_domain_id = rd.id
            GROUP BY d.id, d.domain, dt.name, d.is_own, d.authority_score,
                     d.total_backlinks, d.referring_domains, d.organic_traffic, d.organic_keywords
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_domains_summary');
        DB::statement('DROP VIEW IF EXISTS v_quality_backlinks');
        DB::statement('DROP VIEW IF EXISTS v_keyword_opportunities');
        DB::statement('DROP VIEW IF EXISTS v_current_rankings');
        DB::statement('DROP VIEW IF EXISTS v_keywords_full');
    }
};
