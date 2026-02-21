-- ============================================================================
-- Queries de Validación de Importación MVP
-- ============================================================================

-- 1. CONTEO DE TODAS LAS TABLAS
-- ============================================================================
SELECT 'CONTEO DE TABLAS' AS section;
SELECT '==================' AS separator;

SELECT 'cities' AS tabla, COUNT(*) AS total FROM cities
UNION ALL
SELECT 'categories', COUNT(*) FROM categories
UNION ALL
SELECT 'search_intents', COUNT(*) FROM search_intents
UNION ALL
SELECT 'domain_types', COUNT(*) FROM domain_types
UNION ALL
SELECT 'link_types', COUNT(*) FROM link_types
UNION ALL
SELECT 'gap_types', COUNT(*) FROM gap_types
UNION ALL
SELECT 'domains', COUNT(*) FROM domains
UNION ALL
SELECT 'keywords', COUNT(*) FROM keywords
UNION ALL
SELECT 'keyword_rankings', COUNT(*) FROM keyword_rankings
UNION ALL
SELECT 'referring_domains', COUNT(*) FROM referring_domains
UNION ALL
SELECT 'backlinks', COUNT(*) FROM backlinks;

-- 2. VERIFICAR INTEGRIDAD REFERENCIAL
-- ============================================================================
SELECT '' AS blank;
SELECT 'INTEGRIDAD REFERENCIAL' AS section;
SELECT '======================' AS separator;

-- Keywords sin ciudad asignada pero con ciudad en el nombre
SELECT
    'Keywords sin ciudad pero con ciudad en nombre' AS issue,
    COUNT(*) AS count
FROM keywords
WHERE city_id IS NULL
    AND (
        keyword LIKE '%bogotá%' OR keyword LIKE '%bogota%' OR
        keyword LIKE '%medellín%' OR keyword LIKE '%medellin%' OR
        keyword LIKE '%cali%' OR
        keyword LIKE '%barranquilla%' OR
        keyword LIKE '%cartagena%' OR
        keyword LIKE '%neiva%'
    );

-- Rankings sin keyword válido
SELECT
    'Rankings sin keyword válido' AS issue,
    COUNT(*) AS count
FROM keyword_rankings kr
LEFT JOIN keywords k ON kr.keyword_id = k.id
WHERE k.id IS NULL;

-- Rankings sin domain válido
SELECT
    'Rankings sin domain válido' AS issue,
    COUNT(*) AS count
FROM keyword_rankings kr
LEFT JOIN domains d ON kr.domain_id = d.id
WHERE d.id IS NULL;

-- Backlinks sin referring_domain válido
SELECT
    'Backlinks sin referring_domain válido' AS issue,
    COUNT(*) AS count
FROM backlinks b
LEFT JOIN referring_domains rd ON b.referring_domain_id = rd.id
WHERE rd.id IS NULL;

-- 3. DISTRIBUCIÓN DE KEYWORDS POR CIUDAD
-- ============================================================================
SELECT '' AS blank;
SELECT 'KEYWORDS POR CIUDAD' AS section;
SELECT '===================' AS separator;

SELECT
    COALESCE(c.name, '(Sin ciudad)') AS city,
    COUNT(k.id) AS total_keywords,
    SUM(k.search_volume_co) AS total_volume,
    ROUND(AVG(k.keyword_difficulty), 2) AS avg_kd,
    ROUND(AVG(k.search_volume_co), 0) AS avg_volume
FROM keywords k
LEFT JOIN cities c ON k.city_id = c.id
GROUP BY c.id, c.name
ORDER BY total_keywords DESC;

-- 4. DISTRIBUCIÓN DE RANKINGS POR DOMINIO
-- ============================================================================
SELECT '' AS blank;
SELECT 'RANKINGS POR DOMINIO' AS section;
SELECT '====================' AS separator;

SELECT
    d.domain,
    dt.name AS domain_type,
    COUNT(kr.id) AS total_rankings,
    ROUND(AVG(kr.position), 2) AS avg_position,
    COUNT(DISTINCT kr.keyword_id) AS unique_keywords,
    MAX(kr.snapshot_date) AS last_snapshot
FROM domains d
INNER JOIN domain_types dt ON d.domain_type_id = dt.id
LEFT JOIN keyword_rankings kr ON d.id = kr.domain_id
GROUP BY d.id, d.domain, dt.name
ORDER BY d.is_own DESC, total_rankings DESC;

-- 5. TOP 10 KEYWORDS POR VOLUMEN
-- ============================================================================
SELECT '' AS blank;
SELECT 'TOP 10 KEYWORDS POR VOLUMEN' AS section;
SELECT '============================' AS separator;

SELECT
    k.keyword,
    c.name AS city,
    k.search_volume_co AS volume,
    k.keyword_difficulty AS kd,
    k.cpc_usd AS cpc,
    (SELECT COUNT(*) FROM keyword_rankings WHERE keyword_id = k.id) AS rankings_count
FROM keywords k
LEFT JOIN cities c ON k.city_id = c.id
ORDER BY k.search_volume_co DESC
LIMIT 10;

-- 6. KEYWORDS DE ALTA OPORTUNIDAD
-- ============================================================================
SELECT '' AS blank;
SELECT 'KEYWORDS DE ALTA OPORTUNIDAD (KD <=30, Volumen >=100)' AS section;
SELECT '======================================================' AS separator;

SELECT
    k.keyword,
    c.name AS city,
    k.search_volume_co AS volume,
    k.keyword_difficulty AS kd,
    (SELECT COUNT(*)
     FROM keyword_rankings kr
     INNER JOIN domains d ON kr.domain_id = d.id
     WHERE kr.keyword_id = k.id AND d.is_own = 1) AS we_rank,
    (SELECT MIN(kr.position)
     FROM keyword_rankings kr
     INNER JOIN domains d ON kr.domain_id = d.id
     WHERE kr.keyword_id = k.id) AS best_competitor_position
FROM keywords k
LEFT JOIN cities c ON k.city_id = c.id
WHERE k.keyword_difficulty <= 30
    AND k.search_volume_co >= 100
ORDER BY k.search_volume_co DESC, k.keyword_difficulty ASC
LIMIT 20;

-- 7. DOMINIOS SIN RANKINGS
-- ============================================================================
SELECT '' AS blank;
SELECT 'DOMINIOS SIN RANKINGS' AS section;
SELECT '=====================' AS separator;

SELECT
    d.domain,
    dt.name AS domain_type,
    d.is_own
FROM domains d
INNER JOIN domain_types dt ON d.domain_type_id = dt.id
LEFT JOIN keyword_rankings kr ON d.id = kr.domain_id
WHERE kr.id IS NULL
ORDER BY d.is_own DESC;

-- 8. ESTADÍSTICAS DE BACKLINKS (SI EXISTEN)
-- ============================================================================
SELECT '' AS blank;
SELECT 'ESTADÍSTICAS DE BACKLINKS' AS section;
SELECT '=========================' AS separator;

SELECT
    d.domain,
    COUNT(b.id) AS total_backlinks,
    COUNT(DISTINCT b.referring_domain_id) AS unique_referring_domains,
    SUM(CASE WHEN b.is_spam THEN 1 ELSE 0 END) AS spam_count,
    SUM(CASE WHEN b.is_active THEN 1 ELSE 0 END) AS active_count,
    ROUND(AVG(rd.authority_score), 2) AS avg_referring_as
FROM domains d
LEFT JOIN backlinks b ON d.id = b.target_domain_id
LEFT JOIN referring_domains rd ON b.referring_domain_id = rd.id
WHERE d.is_own = 1
GROUP BY d.id, d.domain
ORDER BY total_backlinks DESC;

-- 9. RESUMEN GENERAL
-- ============================================================================
SELECT '' AS blank;
SELECT 'RESUMEN GENERAL' AS section;
SELECT '===============' AS separator;

SELECT
    'Total Keywords' AS metric,
    COUNT(*) AS value
FROM keywords
UNION ALL
SELECT
    'Keywords con ciudad asignada',
    COUNT(*)
FROM keywords
WHERE city_id IS NOT NULL
UNION ALL
SELECT
    'Keywords sin rankings',
    COUNT(DISTINCT k.id)
FROM keywords k
LEFT JOIN keyword_rankings kr ON k.id = kr.keyword_id
WHERE kr.id IS NULL
UNION ALL
SELECT
    'Total Rankings',
    COUNT(*)
FROM keyword_rankings
UNION ALL
SELECT
    'Dominios propios',
    COUNT(*)
FROM domains
WHERE is_own = 1
UNION ALL
SELECT
    'Dominios competidores',
    COUNT(*)
FROM domains
WHERE is_own = 0
UNION ALL
SELECT
    'Total Backlinks',
    COUNT(*)
FROM backlinks
UNION ALL
SELECT
    'Backlinks activos',
    COUNT(*)
FROM backlinks
WHERE is_active = 1 AND is_spam = 0;
