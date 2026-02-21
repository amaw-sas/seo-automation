# Diseño: Migración de SEO Automation a Laravel

**Fecha**: 2026-01-19
**Estado**: En exploración
**Decisión**: Pendiente selección de arquitectura

---

## Contexto Original

Proyecto SEO automation inicialmente diseñado con:
- **Stack**: TypeScript, Node.js 20+, SQLite/PostgreSQL
- **MCP Server**: Servidor de protocolo de contexto de modelo de Anthropic
- **5 Agentes SEO**: Analista, Auditor, Estratega, Optimizador, Publicador
- **Hosting**: VPS (Hetzner $4.50/mes o Vultr $6-12/mes)
- **Scheduling**: node-cron + pm2

## Razón del Cambio

**Preferencia personal** - Usuario prefiere trabajar con PHP/Laravel

---

## ¿Qué es el MCP Server?

**MCP (Model Context Protocol)** es un protocolo de Anthropic que permite a Claude usar "herramientas" personalizadas.

### Arquitectura con MCP:
```
┌─────────────────────────────────────────┐
│         Claude (Cerebro)                │
│  "Quiero analizar keywords de X sitio"  │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│         MCP Server                      │
│  Traduce solicitudes → llamadas API     │
└──────────────┬──────────────────────────┘
               │
        ┌──────┴──────┬──────┬───────┐
        ▼             ▼      ▼       ▼
    SEMrush      GSC   NeuronW  WordPress
```

**Ejemplo práctico**:
1. Claude dice: "Dame las top 10 keywords de ejemplo.com"
2. MCP Server llama a `semrushService.getTopKeywords('ejemplo.com')`
3. Devuelve resultados a Claude
4. Claude analiza y decide siguiente paso

---

## Opciones de Arquitectura Evaluadas

### Opción A: Laravel + Claude API Directo ⭐ RECOMENDADA

**Arquitectura**:
```php
// Laravel orquesta todo
AnalyzeSiteJob::dispatch($site)
  → ClaudeService->analyze($data)
  → SemrushService->getKeywords()
  → ClaudeService->strategize($keywords)
  → NeuronWriterService->optimize()
```

| Pros | Contras |
|------|---------|
| ✅ Todo en PHP, stack unificado | ❌ Pierdes el MCP (Claude tiene menos contexto) |
| ✅ Control total del flujo | ❌ Más código manual para orquestar |
| ✅ Fácil debugging | ❌ Claude no puede decidir dinámicamente qué herramientas usar |
| ✅ Deploy simple (solo Laravel) | ❌ Menos "inteligente" que con MCP |

**Métricas**:
- Complejidad: Media
- Costo Claude API: Alto (muchas llamadas separadas)
- Inteligencia: Media (tú defines el flujo)
- Deploy: Simple (un solo proceso)

**Flujo recomendado**:
```php
1. Scheduler ejecuta AnalyzeSiteJob diario
2. Job llama Claude: "Analiza esta data de SEMrush: {json}"
3. Claude responde: "Recomienda optimizar X páginas"
4. Job ejecuta OptimizePageJob para cada página
5. Claude genera contenido optimizado
6. Job publica a WordPress
```

---

### Opción B: Laravel Jobs Puro (sin IA dinámica)

**Arquitectura**:
```php
// Flujo completamente predefinido
AnalyzeSiteJob → GetKeywordsJob
              → RankKeywordsJob
              → CreateContentBriefJob
              → OptimizeWithNeuronJob
              → PublishToWordPressJob
```

| Pros | Contras |
|------|---------|
| ✅ Muy predecible y debuggeable | ❌ Cero flexibilidad (flujo fijo) |
| ✅ Más barato (menos llamadas IA) | ❌ No usa capacidad de razonamiento de Claude |
| ✅ Rápido (sin espera de IA) | ❌ Requieres actualizar código para cambiar lógica |
| ✅ Fácil de mantener | ❌ No se adapta a casos especiales |

**Métricas**:
- Complejidad: Baja
- Costo Claude API: Bajo-Medio (solo para generación de contenido)
- Inteligencia: Baja (automatización básica)
- Deploy: Simple

---

### Opción C: Híbrido (Node MCP + Laravel)

**Arquitectura**:
```
Laravel (Scheduler, DB, WordPress)
   ↓
Node.js MCP Server (Claude + herramientas)
   ↓
APIs externas (SEMrush, GSC, etc.)
```

| Pros | Contras |
|------|---------|
| ✅ Mejor de ambos mundos | ❌ Dos stacks = más complejo |
| ✅ Claude con MCP (máxima inteligencia) | ❌ Deploy más complicado (2 procesos) |
| ✅ Laravel maneja lo que hace mejor (DB, cron) | ❌ Comunicación inter-proceso |
| ✅ Node maneja lo que hace mejor (MCP, async) | ❌ Requiere conocer ambos lenguajes |

**Métricas**:
- Complejidad: Alta
- Costo Claude API: Medio (Claude decide cuándo llamar)
- Inteligencia: Alta (Claude orquesta dinámicamente)
- Deploy: Complejo (2 procesos en VPS)

---

## Matriz de Decisión

| Prioridad | Mejor Opción |
|-----------|--------------|
| **Simplicidad máxima** | 🏆 Opción B (Jobs puros) |
| **Control total** | 🏆 Opción A (Claude API directo) |
| **Máxima inteligencia IA** | 🏆 Opción C (Híbrido) |
| **Bajo costo API** | 🏆 Opción B (Jobs puros) |
| **Stack unificado PHP** | 🏆 Opción A o B |
| **Facilidad mantenimiento** | 🏆 Opción B (Jobs puros) |

---

## Información Adicional del Proyecto

### Comparación de Hosting (decisiones previas)

#### Hetzner vs Vultr
- **Hetzner CPX11**: €4.15/mes (~$4.50), 2 vCPU, 2GB RAM, 40GB SSD, 20TB tráfico
- **Vultr Regular**: $6-12/mes, 2 vCPU, 2GB RAM, 55GB SSD, 2TB tráfico

**Decisión previa**: Hetzner para sitios europeos, Vultr para LATAM/global

#### Firebase vs VPS
**Conclusión**: Firebase NO es adecuado
- ❌ Cloud Functions no soportan procesos persistentes
- ❌ No permite ejecutar binarios (Screaming Frog)
- ❌ SQLite requiere filesystem persistente

#### Google Cloud vs Hetzner
**Conclusión**: Hetzner tiene mejor ROI
- Hetzner: $54/año
- GCP e2-small: $336/año
- Diferencia: $282/año sin beneficios proporcionales

### Versionado para Deploy
Estrategia recomendada: **Semantic Versioning con tags**
```bash
# Crear release
git tag -a v1.0.0 -m "Release 1.0.0"
git push origin --tags

# Deploy en VPS
~/update-seo.sh v1.0.0

# Rollback si falla
~/update-seo.sh v0.9.5
```

---

## Próximos Pasos

1. **PENDIENTE**: Usuario debe seleccionar entre Opción A, B o C
2. Una vez seleccionada, diseñar estructura de proyecto Laravel detallada
3. Definir schema de base de datos
4. Crear plan de implementación
5. Setup en VPS (Hetzner o Vultr)

---

## Preguntas Pendientes

- ¿Qué opción de arquitectura prefieres? (A, B, o C)
- ¿Necesitas ver código de ejemplo antes de decidir?
- ¿Los sitios target son europeos o globales? (para elegir hosting)
