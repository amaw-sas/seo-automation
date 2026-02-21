# Guía de Configuración: WordPress Publisher

## Fase 4 - Sistema de Publicación Automática a WordPress

**Fecha**: 16 de febrero de 2026
**Estado**: ✅ Implementado (Pendiente configuración y testing)

---

## 📋 Resumen

El **WordPress Publisher** es el componente final del pipeline de automatización SEO que permite publicar posts generados con IA directamente a sitios WordPress vía REST API.

**Flujo completo**:
```
Datos SEMRush → Análisis SEO → Generación de Contenido (LLM) → Publicación a WordPress
```

---

## 🎯 Características Implementadas

### ✅ Componentes Creados

1. **Exception Classes** (4 archivos)
   - `ValidationException` - Errores de validación pre-publicación
   - `WordPressPublishException` - Errores en WordPress API
   - `ImageUploadException` - Errores al subir imágenes
   - `InvalidCredentialsException` - Credenciales incorrectas

2. **PostValidator** - Validaciones SEO
   - Título: 30-60 caracteres
   - Meta description: 120-160 caracteres
   - Contenido: mínimo 300 palabras
   - Imagen destacada: requerida y existente

3. **WordPressPublisher** - Servicio principal
   - Validación de credenciales
   - Upload de imágenes (featured + inline) a WordPress Media Library
   - Reemplazo automático de URLs locales por URLs de WordPress
   - Creación de posts vía REST API
   - Retry automático con exponential backoff (3 intentos: 0s, 5s, 15s)
   - Logging detallado de operaciones

4. **Comandos Artisan**
   - `seo:publish:post {post-id} --site={site-id}` - Publicación individual
   - `seo:publish:batch --limit=10 --min-quality=70 [--site=1]` - Publicación por lotes

---

## ⚙️ Configuración Inicial

### Paso 1: Configurar WordPress REST API

**En el sitio WordPress**:

1. Ir a **WordPress Admin → Settings → Permalinks**
   - Asegurar que NO esté en "Plain" (debe ser Pretty URLs)
   - Recomendado: "Post name"

2. Verificar que REST API esté habilitado:
   ```bash
   curl https://alquilatucarro.com.co/wp-json/wp/v2/posts
   ```
   Debe retornar JSON (no error 404)

### Paso 2: Crear Application Password

**En WordPress**:

1. Ir a **Users → Your Profile**
2. Scroll down a **"Application Passwords"**
3. Enter name: `SEO Automation`
4. Click **"Add New Application Password"**
5. **Copiar el password** (formato: `xxxx xxxx xxxx xxxx`)

   ⚠️ **IMPORTANTE**: El password solo se muestra UNA vez. Guárdalo inmediatamente.

### Paso 3: Configurar .env

Agregar las siguientes variables al archivo `.env`:

```bash
# WordPress REST API Configuration
WP_USERNAME=admin
WP_APP_PASSWORD="xxxx xxxx xxxx xxxx"
```

### Paso 4: Ejecutar Seeder

```bash
cd /home/pabloandi/proyectos/amaw/seo-automation/seo-automation

# Crear sitio WordPress en BD
php artisan db:seed --class=WordPressSiteSeeder
```

### Paso 5: Verificar Configuración

```bash
# Listar sitios WordPress configurados
php artisan tinker
>>> App\Models\WordPressSite::all();
>>> exit
```

---

## 🚀 Uso de Comandos

### Publicación Individual

**Publicar un post específico**:
```bash
php artisan seo:publish:post 6 --site=1
```

**Salida esperada**:
```
Publishing post #6 to site #1 (https://alquilatucarro.com.co)...

✓ Validating: Title, meta description, content, image
✓ Uploaded featured image
✓ Uploaded 2 inline images
✓ Replaced image URLs in content
✓ Published to WordPress → post_id: 8842

Post published successfully!
URL: https://alquilatucarro.com.co/2026/02/alquiler-de-carros-en-bogota...
Time: 3.8s

+-------------------+------------------------------------------------------+
| Metric            | Value                                                |
+-------------------+------------------------------------------------------+
| Post ID           | 6                                                    |
| WordPress Post ID | 8842                                                 |
| Title             | Alquiler de Carros en Bogotá: Guía Completa 2026   |
| Word Count        | 1927                                                 |
| Quality Score     | 100/100                                              |
| Images Uploaded   | 3                                                    |
| Duration          | 3.8s                                                 |
| Published URL     | https://alquilatucarro.com.co/2026/02/post...       |
+-------------------+------------------------------------------------------+
```

### Publicación por Lotes

**Publicar múltiples posts con filtros**:
```bash
# Publicar hasta 5 posts con quality >= 80
php artisan seo:publish:batch --limit=5 --min-quality=80

# Publicar a sitio específico
php artisan seo:publish:batch --limit=10 --site=1

# Modo verbose (muestra errores detallados + auto-retry)
php artisan seo:publish:batch --limit=5 --verbose
```

**Salida esperada**:
```
Publishing batch: limit=5, min_quality=80
Found 9 posts matching criteria

[1/5] Post #42 "Alquiler de Carros en Bogotá..." ✓
      https://alquilatucarro.com.co/2026/02/post-42

[2/5] Post #44 "Los Mejores Carros para Alquilar..." ✓
      https://alquilatucarro.com.co/2026/02/post-44

[3/5] Post #47 "Documentos Necesarios..." ✓
      https://alquilatucarro.com.co/2026/02/post-47

[4/5] Post #49 "Precios de Alquiler..." ✓
      https://alquilatucarro.com.co/2026/02/post-49

[5/5] Post #43 "Seguros de Alquiler..." ✓
      https://alquilatucarro.com.co/2026/02/post-43

Summary:
+-------------------+----------+
| Metric            | Value    |
+-------------------+----------+
| Total Processed   | 5        |
| ✓ Published       | 5        |
| ✗ Failed          | 0        |
| ⏱ Total Time     | 19.2s    |
| ⏱ Avg Time       | 3.8s/post|
+-------------------+----------+

✓ 5 posts published successfully!
```

---

## 🤖 Automatización con Laravel Scheduler

### Configurar Cron Job

**En el servidor (una sola vez)**:
```bash
crontab -e

# Agregar:
* * * * * cd /var/www/seo-automation && php artisan schedule:run >> /dev/null 2>&1
```

### Configurar Tareas Programadas

**Editar** `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // ESTRATEGIA 1: Publicación constante (cada hora)
    $schedule->command('seo:publish:batch --limit=5 --min-quality=80')
             ->hourly()
             ->withoutOverlapping()
             ->onOneServer();

    // ESTRATEGIA 2: Horarios específicos (9 AM - 6 PM)
    $schedule->command('seo:publish:batch --site=1 --limit=3')
             ->everyThreeHours()
             ->between('9:00', '18:00');

    // ESTRATEGIA 3: Publicación diaria (10 AM)
    $schedule->command('seo:publish:batch --site=1 --limit=5')
             ->dailyAt('10:00');

    // ESTRATEGIA 4: Publicación semanal masiva (Domingo 6 AM)
    $schedule->command('seo:publish:batch --limit=20 --min-quality=90')
             ->weeklyOn(0, '06:00');
}
```

### Verificar Tareas

```bash
# Listar tareas programadas
php artisan schedule:list

# Probar ejecución manual
php artisan schedule:run

# Ver próxima ejecución
php artisan schedule:work
```

---

## 🔍 Testing y Validación

### Test 1: Verificar Credenciales

```bash
php artisan tinker
>>> $site = App\Models\WordPressSite::first();
>>> $response = Http::withBasicAuth($site->wp_username, decrypt($site->wp_app_password))
                    ->get("{$site->wp_rest_api_url}/wp-json/wp/v2/users/me");
>>> $response->successful(); // Debe retornar true
>>> $response->json(); // Debe mostrar datos del usuario
>>> exit
```

### Test 2: Publicar Post de Prueba

```bash
# Crear post draft simple para testing
php artisan tinker
>>> $post = App\Models\GeneratedPost::first();
>>> $post->status; // Debe ser 'draft'
>>> exit

# Publicar
php artisan seo:publish:post {post-id} --site=1
```

### Test 3: Verificar en WordPress

1. Ir a **WordPress Admin → Posts**
2. Buscar el post recién publicado
3. Verificar:
   - ✅ Título correcto
   - ✅ Contenido completo
   - ✅ Imagen destacada (Featured Image)
   - ✅ Imágenes inline en el contenido
   - ✅ Meta description (con Yoast SEO)

### Test 4: Verificar Logs

```bash
# Ver logs de publicación
tail -f storage/logs/laravel.log

# Buscar errores
grep -i "error" storage/logs/laravel.log | tail -20
```

---

## 🔧 Troubleshooting

### Error: "Invalid credentials"

**Causa**: `wp_app_password` incorrecto o usuario sin permisos.

**Solución**:
1. Regenerar Application Password en WordPress
2. Actualizar `.env` con nuevo password
3. Re-ejecutar seeder: `php artisan db:seed --class=WordPressSiteSeeder`

### Error: "WordPress API error: rest_cannot_create"

**Causa**: Usuario no tiene permisos de publicación.

**Solución**:
- El usuario debe tener rol **Editor** o **Administrator**
- Verificar en WordPress: Users → Edit User → Role

### Error: "Image upload failed: file not found"

**Causa**: La imagen no existe en `storage/app/public/generated/`.

**Solución**:
1. Verificar que el symbolic link exista:
   ```bash
   php artisan storage:link
   ```
2. Regenerar el post con imágenes:
   ```bash
   php artisan seo:generate:post --keyword=37720 --llm=xai --image-llm=xai
   ```

### Error: "Connection timeout"

**Causa**: WordPress no responde o red lenta.

**Solución**:
- El sistema intentará 3 veces automáticamente (exponential backoff)
- Verificar que WordPress esté online: `curl https://sitio.com/wp-json`
- Revisar firewall del servidor

### Error: "Invalid category ID"

**Causa**: `default_category_id` no existe en WordPress.

**Solución**:
1. En WordPress: Posts → Categories
2. Copiar el ID de la categoría deseada (aparece en URL al editar)
3. Actualizar en BD:
   ```bash
   php artisan tinker
   >>> $site = App\Models\WordPressSite::first();
   >>> $site->default_category_id = 12; // ID correcto
   >>> $site->save();
   ```

---

## 📊 Métricas y Monitoring

### Dashboard de Publicaciones

**Ver estadísticas**:
```bash
php artisan tinker
>>> $site = App\Models\WordPressSite::first();
>>> "Total posts publicados: " . $site->total_posts_published;
>>> "Última publicación: " . $site->last_published_at;
>>> "Posts pendientes: " . App\Models\GeneratedPost::where('status', 'draft')->count();
>>> exit
```

### Logs de Publicación

**Formato de logs**:
```
[2026-02-16 10:15:23] INFO: Publishing post #42 to site #1
[2026-02-16 10:15:24] INFO: Uploaded featured image → media_id: 4523
[2026-02-16 10:15:26] INFO: Uploaded 2 inline images → media_ids: 4524, 4525
[2026-02-16 10:15:27] INFO: Created WordPress post → post_id: 8842
[2026-02-16 10:15:27] INFO: Post #42 published successfully in 4.2s

[2026-02-16 10:18:45] WARNING: Upload failed for post #49, retrying (attempt 2/3)
[2026-02-16 10:18:50] INFO: Retry successful for post #49

[2026-02-16 10:22:10] ERROR: Failed to publish post #51 after 3 attempts
[2026-02-16 10:22:10] ERROR: WordPress API error: Invalid category ID 999
```

---

## 🎯 Casos de Uso

### Caso 1: Publicación Manual Controlada

**Escenario**: Revisar posts antes de publicar.

**Workflow**:
1. Generar posts: `php artisan seo:generate:post --keyword=X`
2. Revisar en BD: `App\Models\GeneratedPost::latest()->first()`
3. Publicar manualmente: `php artisan seo:publish:post {id} --site=1`

### Caso 2: Publicación Automatizada 24/7

**Escenario**: Generación y publicación continua.

**Workflow**:
```php
// app/Console/Kernel.php

// Generar 10 posts diarios a las 2 AM
$schedule->command('seo:generate:batch --limit=10 --min-quality=80')
         ->dailyAt('02:00');

// Publicar 5 posts cada hora
$schedule->command('seo:publish:batch --limit=5 --min-quality=80')
         ->hourly()
         ->withoutOverlapping();
```

**Resultado esperado**: ~120 posts publicados/mes en piloto automático.

### Caso 3: Publicación por Ciudad

**Escenario**: Publicar contenido segmentado geográficamente.

**Workflow**:
```bash
# Generar posts para Neiva
php artisan seo:generate:batch --city=neiva --count=10

# Publicar solo esos posts
php artisan tinker
>>> $posts = App\Models\GeneratedPost::where('status', 'draft')
                ->whereHas('primaryKeyword', function($q) {
                    $q->whereHas('city', function($q2) {
                        $q2->where('name', 'Neiva');
                    });
                })->pluck('id');
>>> foreach($posts as $id) {
>>>     Artisan::call('seo:publish:post', ['post' => $id, '--site' => 1]);
>>> }
```

---

## 📈 ROI Esperado

**Inversión**:
- Infraestructura: $6/mes (DigitalOcean)
- LLM (generación): ~$1.47 por 10 posts
- WordPress Publisher: $0 (REST API gratuito)
- **Total**: ~$50/mes

**Retorno**:
- Posts publicados: 300/mes (10/día)
- Tráfico esperado: +15,000-25,000 visitas/mes
- Leads esperados: ~150-300/mes
- **ROI estimado**: 300-600%

---

## ✅ Checklist de Implementación

### Pre-Requisitos
- [ ] WordPress con REST API habilitado
- [ ] Application Password generado
- [ ] Variables `WP_USERNAME` y `WP_APP_PASSWORD` en `.env`
- [ ] Categoría y autor por defecto identificados

### Configuración Inicial
- [ ] Ejecutar seeder: `php artisan db:seed --class=WordPressSiteSeeder`
- [ ] Verificar credenciales con Test 1
- [ ] Crear symbolic link: `php artisan storage:link`

### Testing
- [ ] Publicar post de prueba (Test 2)
- [ ] Verificar post en WordPress (Test 3)
- [ ] Revisar logs (Test 4)

### Producción
- [ ] Configurar scheduler en `app/Console/Kernel.php`
- [ ] Agregar cron job en servidor
- [ ] Verificar tareas: `php artisan schedule:list`
- [ ] Monitorear logs por 24 horas

---

## 🔐 Seguridad

### Best Practices

1. **Credenciales**:
   - ✅ Usar Application Passwords (no contraseña real)
   - ✅ Encriptar `wp_app_password` en BD con `encrypt()`
   - ✅ Guardar en `.env`, no en código
   - ❌ NUNCA commitear credenciales en Git

2. **Validación**:
   - ✅ Validar formato de URLs antes de reemplazar
   - ✅ Sanitizar contenido HTML (WordPress lo hace automáticamente)
   - ✅ Verificar que imágenes existen antes de upload

3. **Rate Limiting**:
   - ✅ Respetar límites de WordPress REST API
   - ✅ Usar `withoutOverlapping()` en scheduler
   - ✅ Max 5-10 posts por batch para no saturar

4. **Error Handling**:
   - ✅ Capturar excepciones HTTP
   - ✅ Log de errores detallado
   - ✅ No exponer credenciales en logs

---

## 📞 Soporte

### Logs Útiles

```bash
# Ver últimos 50 logs
tail -50 storage/logs/laravel.log

# Filtrar solo errores de publicación
grep "WordPressPublish" storage/logs/laravel.log

# Monitorear en tiempo real
tail -f storage/logs/laravel.log | grep -i "publish"
```

### Comandos de Debug

```bash
# Verificar posts pendientes
php artisan tinker
>>> App\Models\GeneratedPost::where('status', 'draft')->count();

# Ver último post generado
>>> App\Models\GeneratedPost::latest()->first();

# Ver configuración de sitio WordPress
>>> App\Models\WordPressSite::first();
```

---

## 🚀 Próximos Pasos

Una vez configurado y testeado:

1. **Monitoreo**: Revisar logs diariamente por una semana
2. **Optimización**: Ajustar horarios de scheduler según tráfico
3. **Escalado**: Agregar más sitios WordPress si es necesario
4. **Métricas**: Configurar Google Analytics para medir impacto

---

## 📝 Changelog

### 2026-02-16 - v1.0.0
- ✅ Implementación inicial completa
- ✅ 4 exception classes
- ✅ PostValidator con validaciones SEO
- ✅ WordPressPublisher con retry logic
- ✅ Comandos `publish:post` y `publish:batch`
- ✅ Seeder de ejemplo
- ✅ Documentación completa

---

**Estado**: ✅ Listo para configuración y testing
**Tiempo de implementación**: 6 horas
**Archivos creados**: 8
**Líneas de código**: ~800 LOC
