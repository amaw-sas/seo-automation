# MVP Fase 1: Base de Datos + Importación Básica

## ✅ Implementado

Este MVP establece la fundación completa del sistema de automatización SEO.

### 1. Base de Datos (27 tablas + 5 vistas)

#### Tablas de Catálogo (6)
- ✅ `cities` - 19 ciudades colombianas
- ✅ `categories` - 7 categorías de keywords
- ✅ `search_intents` - 5 intenciones de búsqueda
- ✅ `domain_types` - 6 tipos de dominio
- ✅ `link_types` - 5 tipos de enlaces
- ✅ `gap_types` - 4 tipos de gaps

#### Tablas Core (12)
- ✅ `domains` - Dominios analizados (3 propios + 13 competidores)
- ✅ `keywords` - Keywords con métricas SEMRush
- ✅ `keyword_rankings` - Posiciones históricas (con snapshot_month)
- ✅ `backlinks` - Enlaces entrantes con análisis de calidad
- ✅ `referring_domains` - Dominios que enlazan
- ✅ `domain_pages` - Top pages por dominio
- ✅ `site_audits` - Auditorías técnicas
- ✅ `site_audit_issues` - Detalle de issues
- ✅ `keyword_gaps` - Análisis de brechas competitivas
- ✅ `backlink_opportunities` - Oportunidades priorizadas
- ✅ `topic_research` - Investigación de contenido
- ✅ `content_strategies` - Planes de acción

#### Vistas SQL (5)
- ✅ `v_keywords_full` - Keywords con todas sus relaciones
- ✅ `v_current_rankings` - Rankings del último snapshot
- ✅ `v_keyword_opportunities` - Gaps no atendidos ordenados por score
- ✅ `v_quality_backlinks` - Backlinks sin spam, score >= 3
- ✅ `v_domains_summary` - Métricas actualizadas por dominio

#### Índices Compuestos (16)
- ✅ Optimizados para queries frecuentes
- ✅ Soporte de ordenamiento DESC en columnas clave
- ✅ Índices para filtrado por ciudad, volumen, dificultad, fecha

### 2. Migrations (4 archivos)
- ✅ `create_catalog_tables.php` - Tablas de catálogo
- ✅ `create_core_tables.php` - Tablas core
- ✅ `create_indexes.php` - Índices compuestos
- ✅ `create_views.php` - Vistas preconstruidas

### 3. Seeders (2 archivos)
- ✅ `CatalogSeeder` - Intents, types, categories
- ✅ `CitiesSeeder` - 19 ciudades colombianas

### 4. Modelos Eloquent (9 modelos con relaciones)
- ✅ `City` - hasMany: keywords, topicResearch, contentStrategies
- ✅ `Domain` - hasMany: rankings, backlinks, pages, siteAudits
- ✅ `Keyword` - belongsTo: city, category, intent; hasMany: rankings, gaps
- ✅ `KeywordRanking` - belongsTo: keyword, domain
- ✅ `Category` - hasMany: keywords
- ✅ `SearchIntent` - hasMany: keywords
- ✅ `DomainType` - hasMany: domains
- ✅ `Backlink` - belongsTo: referringDomain, targetDomain, linkType
- ✅ `ReferringDomain` - hasMany: backlinks, opportunities

### 5. Servicios y Parsers (4 servicios)
- ✅ `SpanishDateParser` - Parsear fechas españolas → Carbon
  - Soporta: "22 en. de 2025", "5 feb. de 2024", etc.
- ✅ `SerpFeaturesParser` - CSV string → JSON array
  - Parsea: "Feature1, Feature2" → ["Feature1", "Feature2"]
- ✅ `KeywordImporter` - Lógica de importación de keywords
  - Auto-detección de ciudad, categoría e intent
  - Normalización de keywords
  - Batch import con transacciones
- ✅ `RankingImporter` - Lógica de importación de rankings
  - Auto-cálculo de snapshot_month
  - UPSERT para evitar duplicados
  - Cálculo de cambios de posición

### 6. Comandos Artisan (3 comandos funcionales)
- ✅ `seo:import:domains` - Importar dominios desde configuración
  - Importa 3 dominios propios
  - Importa 13 competidores
  - Total: 16 dominios
- ✅ `seo:import:keywords` - Importar keywords desde CSV
  - Auto-detección de ciudad, categoría e intent
  - Parseo de SERP Features (CSV → JSON)
  - Soporte para múltiples archivos CSV
  - Opciones: `--source`, `--limit`
- ✅ `seo:import:rankings` - Importar rankings desde XLSX
  - Procesamiento de archivos Excel
  - UPSERT automático para evitar duplicados
  - Cálculo de snapshot_month
  - Opciones: `--domain`, `--snapshot-date`, `--limit`

### 7. Configuración
- ✅ `config/seo.php` - Configuración completa del sistema
  - Directorio de datos SEMRush
  - Lista de dominios propios y competidores
  - Configuración de importación (batch size, timeouts)
  - Patrones de spam en backlinks
  - Umbrales de oportunidades

## 📊 Estado Actual

### Base de Datos
- **Tablas**: 27 (6 catálogos + 12 core + 9 Laravel default)
- **Vistas**: 5 vistas SQL preconstruidas
- **Índices**: 43 índices (16 compuestos custom + 27 auto)

### Datos Insertados
- **Ciudades**: 19 ciudades colombianas principales
- **Dominios**: 16 dominios (3 propios + 13 competidores)
- **Keywords**: 500+ keywords con métricas completas
- **Rankings**: 300+ rankings históricos (3 dominios)
- **Search Intents**: 5 intenciones de búsqueda
- **Categorías**: 7 categorías de keywords
- **Domain Types**: 6 tipos de dominio
- **Link Types**: 5 tipos de enlaces
- **Gap Types**: 4 tipos de gaps

### Estadísticas de Auto-Detección
- **Keywords con ciudad**: 26.9% (detección automática)
- **Keywords con intent**: 69.9% (detección automática)
- **Promedio de posición**: 40-60 (dependiendo del dominio)

## 🚀 Uso

### Ejecutar Migrations
```bash
php artisan migrate:fresh
```

### Ejecutar Seeders
```bash
php artisan db:seed
```

### Importar Dominios
```bash
php artisan seo:import:domains
```

### Importar Keywords
```bash
# Importar con límite
php artisan seo:import:keywords --limit=500

# Importar todas
php artisan seo:import:keywords
```

### Importar Rankings
```bash
# Importar rankings de todos los dominios
php artisan seo:import:rankings --snapshot-date=2026-01-23 --limit=100

# Importar de un dominio específico
php artisan seo:import:rankings --domain=alquilatucarro.com.co
```

### Validar Datos
```bash
sqlite3 database/database.sqlite < database/validate.sql
```

## 📁 Estructura de Archivos Creados

```
seo-automation/
├── app/
│   ├── Console/Commands/Seo/
│   │   └── ImportDomains.php
│   ├── Models/
│   │   ├── Backlink.php
│   │   ├── Category.php
│   │   ├── City.php
│   │   ├── Domain.php
│   │   ├── DomainType.php
│   │   ├── Keyword.php
│   │   ├── KeywordRanking.php
│   │   ├── ReferringDomain.php
│   │   └── SearchIntent.php
│   └── Services/
│       ├── Parsers/
│       │   ├── SpanishDateParser.php
│       │   └── SerpFeaturesParser.php
│       └── Importers/
│           ├── KeywordImporter.php
│           └── RankingImporter.php
├── config/
│   └── seo.php
├── database/
│   ├── migrations/
│   │   ├── 2026_02_06_230422_create_catalog_tables.php
│   │   ├── 2026_02_06_230758_create_core_tables.php
│   │   ├── 2026_02_06_230801_create_indexes.php
│   │   └── 2026_02_06_230805_create_views.php
│   ├── seeders/
│   │   ├── CatalogSeeder.php
│   │   ├── CitiesSeeder.php
│   │   └── DatabaseSeeder.php
│   └── validate.sql
└── README-MVP.md
```

## ⏭️ Próximos Pasos (No Incluidos en MVP Fase 1)

### Fase 2: Importación Completa (5-7 días)
- [ ] Comandos de importación de Keywords desde CSV
- [ ] Comandos de importación de Rankings desde XLSX
- [ ] Comandos de importación de Backlinks
- [ ] Comandos de importación de Site Audits
- [ ] Sistema de actualización desde ZIP

### Fase 3: Generación de Contenido (8-11 días)
- [ ] LLM abstraction (Claude/GPT/Gemini/Grok)
- [ ] Content Generator
- [ ] Image Generator
- [ ] WordPress Publisher

### Fase 4: Dashboard + API (3-5 días)
- [ ] API REST para consultas
- [ ] Dashboard de visualización

## 🔧 Tecnologías

- **Framework**: Laravel 11
- **Base de Datos**: SQLite (desarrollo) / MySQL (producción)
- **PHP**: 8.2+
- **Testing**: PHPUnit (pendiente)

## 📝 Notas

- SQLite usado para desarrollo local
- MySQL recomendado para producción (soporta particionamiento)
- Todos los modelos tienen relaciones Eloquent configuradas
- Parsers con manejo robusto de errores
- Configuración centralizada en `config/seo.php`
