# Comandos de Importación SEO

## Comandos Disponibles

### 1. Importar Dominios
Importa los dominios propios y competidores desde la configuración.

```bash
php artisan seo:import:domains
```

**Resultado:**
- Importa 3 dominios propios
- Importa 13 competidores
- Total: 16 dominios

---

### 2. Importar Keywords
Importa keywords desde archivos CSV del magic-tool de SEMRush.

```bash
# Importar todas las keywords
php artisan seo:import:keywords

# Importar con límite
php artisan seo:import:keywords --limit=500

# Importar desde directorio específico
php artisan seo:import:keywords --source=magic-tool
```

**Características:**
- ✅ Auto-detección de ciudad desde el texto de la keyword
- ✅ Auto-detección de categoría según patrones
- ✅ Auto-detección de search intent
- ✅ Parseo de SERP Features (CSV → JSON)
- ✅ Procesamiento de múltiples archivos CSV
- ✅ Manejo de errores robusto

**Opciones:**
- `--source` - Directorio fuente (default: magic-tool)
- `--limit` - Límite de keywords a importar

**Archivos procesados:**
- `Keywords de alquiler de carros en Colombia.csv`
- `Keywords de rutas.csv`
- `Keywords de turismo en Colombia.csv`
- `Keywords de viajes y agencias.csv`

---

### 3. Importar Rankings
Importa rankings desde archivos organic-keywords.xlsx de cada dominio.

```bash
# Importar rankings de todos los dominios
php artisan seo:import:rankings

# Importar rankings de un dominio específico
php artisan seo:import:rankings --domain=alquilatucarro.com.co

# Importar con límite por dominio
php artisan seo:import:rankings --limit=100

# Especificar fecha del snapshot
php artisan seo:import:rankings --snapshot-date=2026-01-23
```

**Características:**
- ✅ Crea keywords automáticamente si no existen
- ✅ UPSERT para evitar duplicados
- ✅ Cálculo automático de snapshot_month
- ✅ Procesamiento de archivos Excel (XLSX)
- ✅ Manejo de múltiples formatos de columnas

**Opciones:**
- `--domain` - Dominio específico a importar
- `--snapshot-date` - Fecha del snapshot (default: hoy)
- `--limit` - Límite de rankings por dominio

**Archivos procesados:**
- `mis-dominios/alquilatucarro/organic-keywords.xlsx`
- `mis-dominios/alquilame/organic-keywords.xlsx`
- `mis-dominios/alquicarros/organic-keywords.xlsx`

---

## Flujo de Importación Completo

### Paso 1: Setup Inicial
```bash
# Ejecutar migrations
php artisan migrate:fresh

# Ejecutar seeders (catálogos y ciudades)
php artisan db:seed
```

### Paso 2: Importar Dominios
```bash
php artisan seo:import:domains
```

### Paso 3: Importar Keywords
```bash
# Importar todas las keywords (puede tomar varios minutos)
php artisan seo:import:keywords

# O importar con límite para pruebas
php artisan seo:import:keywords --limit=500
```

### Paso 4: Importar Rankings
```bash
# Importar rankings de todos los dominios
php artisan seo:import:rankings --snapshot-date=2026-01-23

# O por dominio
php artisan seo:import:rankings --domain=alquilatucarro.com.co --snapshot-date=2026-01-23
```

---

## Validación de Datos

### Verificar conteos
```bash
php artisan tinker
```

```php
// Conteos básicos
DB::table('domains')->count();        // 16 dominios
DB::table('keywords')->count();       // ~500+ keywords
DB::table('keyword_rankings')->count(); // ~300+ rankings
DB::table('cities')->count();         // 19 ciudades

// Keywords con ciudad detectada
DB::table('keywords')->whereNotNull('city_id')->count();

// Rankings por dominio
DB::table('keyword_rankings')
    ->join('domains', 'keyword_rankings.domain_id', '=', 'domains.id')
    ->select('domains.domain', DB::raw('count(*) as total'))
    ->groupBy('domains.domain')
    ->get();
```

---

## Auto-Detección de Datos

### Detección de Ciudad
El sistema detecta automáticamente la ciudad desde el texto de la keyword:

**Ejemplos:**
- `"alquiler de carros bogota"` → Ciudad: **Bogotá**
- `"rentar carro medellin"` → Ciudad: **Medellín**
- `"alquiler carros en cali"` → Ciudad: **Cali**

**Ciudades soportadas:** 19 ciudades principales de Colombia

### Detección de Categoría
El sistema detecta la categoría según patrones en la keyword:

**Ejemplos:**
- `"alquiler de carros suv"` → Categoría: **Tipo Vehículo**
- `"alquiler carros toyota"` → Categoría: **Marca**
- `"alquiler de carros por dia"` → Categoría: **Temporal**

**Categorías:** 7 categorías disponibles

### Detección de Search Intent
El sistema detecta la intención de búsqueda:

**Ejemplos:**
- `"alquiler de carros"` → Intent: **Transactional**
- `"mejor alquiler de carros"` → Intent: **Commercial**
- `"como alquilar un carro"` → Intent: **Informational**

**Intents:** 5 intenciones disponibles

---

## Parsers Integrados

### SpanishDateParser
Parsea fechas en español a Carbon.

```php
use App\Services\Parsers\SpanishDateParser;

$date = SpanishDateParser::parse("22 en. de 2025");
// Carbon: 2025-01-22
```

### SerpFeaturesParser
Parsea SERP features de CSV a JSON.

```php
use App\Services\Parsers\SerpFeaturesParser;

$features = SerpFeaturesParser::parse("Featured Snippet, Image Pack, Video");
// ["Featured Snippet", "Image Pack", "Video"]
```

---

## Configuración

La configuración de importación está en `config/seo.php`:

```php
'semrush_data_dir' => env('SEMRUSH_DATA_DIR', base_path('../semrushdiego')),

'import' => [
    'batch_size' => 1000,
    'chunk_size' => 500,
    'max_execution_time' => 3600,
],
```

---

## Troubleshooting

### Error: "Directorio no encontrado"
```bash
# Verificar que existe el directorio semrushdiego
ls -la ../semrushdiego/

# Actualizar configuración si es necesario
# Editar .env: SEMRUSH_DATA_DIR=/ruta/completa/a/semrushdiego
```

### Error: "No se encontraron archivos CSV"
```bash
# Verificar archivos en magic-tool
ls -la ../semrushdiego/keywords/magic-tool/

# Asegurarse que los archivos tienen extensión .csv
```

### Error: "No se encontró organic-keywords.xlsx"
```bash
# Verificar estructura de carpetas
ls -la ../semrushdiego/mis-dominios/alquilatucarro/

# Los archivos deben estar en:
# mis-dominios/{dominio}/organic-keywords.xlsx
```

### Performance: Importación lenta
```bash
# Usar límites para procesar por lotes
php artisan seo:import:keywords --limit=1000
php artisan seo:import:rankings --limit=200

# Aumentar memoria de PHP si es necesario
php -d memory_limit=512M artisan seo:import:keywords
```

---

## Próximos Comandos (Fase 2)

- [ ] `seo:import:backlinks` - Importar backlinks
- [ ] `seo:import:site-audits` - Importar auditorías
- [ ] `seo:import:keyword-gaps` - Importar gaps
- [ ] `seo:import:topics` - Importar topic research
- [ ] `seo:import:zip` - Importar desde ZIP export
- [ ] `seo:update:monthly` - Actualización mensual automática
