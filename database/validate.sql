-- Script de validación de integridad de datos
-- MVP Fase 1: Base de Datos + Importación Básica

-- ============================================
-- 1. Verificar conteos de tablas principales
-- ============================================
SELECT 'cities' AS tabla, COUNT(*) AS total FROM cities
UNION ALL
SELECT 'domains', COUNT(*) FROM domains
UNION ALL
SELECT 'keywords', COUNT(*) FROM keywords
UNION ALL
SELECT 'keyword_rankings', COUNT(*) FROM keyword_rankings
UNION ALL
SELECT 'backlinks', COUNT(*) FROM backlinks
UNION ALL
SELECT 'referring_domains', COUNT(*) FROM referring_domains;

-- ============================================
-- 2. Verificar catálogos completos
-- ============================================
SELECT 'search_intents' AS catalogo, COUNT(*) AS total FROM search_intents
UNION ALL
SELECT 'domain_types', COUNT(*) FROM domain_types
UNION ALL
SELECT 'link_types', COUNT(*) FROM link_types
UNION ALL
SELECT 'gap_types', COUNT(*) FROM gap_types
UNION ALL
SELECT 'categories', COUNT(*) FROM categories;

-- ============================================
-- 3. Verificar dominios propios vs competidores
-- ============================================
SELECT
    CASE WHEN is_own = 1 THEN 'Propios' ELSE 'Competidores' END AS tipo,
    COUNT(*) AS total
FROM domains
GROUP BY is_own;

-- ============================================
-- 4. Verificar ciudades por región
-- ============================================
SELECT region, COUNT(*) AS ciudades
FROM cities
GROUP BY region
ORDER BY ciudades DESC;

-- ============================================
-- 5. Verificar que las vistas existen
-- ============================================
SELECT name AS vista
FROM sqlite_master
WHERE type = 'view'
ORDER BY name;
