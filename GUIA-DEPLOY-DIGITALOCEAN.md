# Guía de Deploy a DigitalOcean - Sistema SEO Automation

**Fecha**: 2026-02-09
**Droplet**: $6/mes Basic (1GB RAM, 1 vCPU, 25GB SSD)
**Stack**: Ubuntu 24.04 LTS + Nginx + MySQL 8.0 + PHP 8.2

---

## 📋 Pre-requisitos

- ✅ Cuenta de DigitalOcean con droplet creado
- ✅ Acceso SSH al droplet (IP + password/key)
- ⬜ Dominio opcional (ej: seo.alquilatucarro.com.co)

---

## Paso 1: Conectar al Droplet

### 1.1 Obtener IP del Droplet

Desde el panel de DigitalOcean:
1. Ir a **Droplets**
2. Copiar la **IP pública** (ej: 157.245.x.x)

### 1.2 Conectar vía SSH

```bash
# Conectar como root (primera vez)
ssh root@TU_IP_DROPLET

# Si usas clave SSH en Windows WSL:
ssh -i ~/.ssh/id_rsa root@TU_IP_DROPLET
```

---

## Paso 2: Configuración Inicial del Servidor

### 2.1 Actualizar Sistema

```bash
apt update && apt upgrade -y
```

### 2.2 Crear Usuario Deploy (NO usar root)

```bash
# Crear usuario
adduser deploy
# Responder preguntas (nombre, password, etc.)

# Agregar a sudo
usermod -aG sudo deploy

# Configurar SSH para usuario deploy
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/ 2>/dev/null || echo "No SSH keys found"
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys 2>/dev/null || true
```

### 2.3 Salir y Reconectar como Deploy

```bash
# Salir de sesión root
exit

# Conectar como deploy
ssh deploy@TU_IP_DROPLET
```

---

## Paso 3: Instalar Stack LEMP

### 3.1 Instalar Nginx

```bash
sudo apt install -y nginx

# Verificar instalación
nginx -v

# Iniciar y habilitar
sudo systemctl start nginx
sudo systemctl enable nginx

# Verificar status
sudo systemctl status nginx
```

Abrir navegador en `http://TU_IP_DROPLET` → Deberías ver página de bienvenida de Nginx ✅

### 3.2 Instalar MySQL 8.0

```bash
sudo apt install -y mysql-server

# Verificar instalación
mysql --version

# Iniciar y habilitar
sudo systemctl start mysql
sudo systemctl enable mysql
```

### 3.3 Configurar MySQL

```bash
# Ejecutar configuración segura
sudo mysql_secure_installation

# Responder:
# - Validate Password Component? → y (sí)
# - Password validation policy → 1 (MEDIUM)
# - New password → [TU_PASSWORD_SEGURO]
# - Remove anonymous users? → y
# - Disallow root login remotely? → y
# - Remove test database? → y
# - Reload privilege tables? → y
```

### 3.4 Crear Base de Datos y Usuario

```bash
# Conectar a MySQL como root
sudo mysql

# Dentro de MySQL, ejecutar:
CREATE DATABASE seo_automation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'seo_user'@'localhost' IDENTIFIED BY 'TU_PASSWORD_MYSQL';

GRANT ALL PRIVILEGES ON seo_automation.* TO 'seo_user'@'localhost';

FLUSH PRIVILEGES;

# Verificar
SHOW DATABASES;
SELECT User, Host FROM mysql.user WHERE User = 'seo_user';

# Salir
EXIT;
```

**Anotar credenciales**:
- Database: `seo_automation`
- Usuario: `seo_user`
- Password: `[TU_PASSWORD_MYSQL]`

### 3.5 Instalar PHP 8.2

```bash
# Agregar repositorio PHP
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

# Instalar PHP 8.2 con extensiones para Laravel
sudo apt install -y \
    php8.2-fpm \
    php8.2-cli \
    php8.2-mysql \
    php8.2-xml \
    php8.2-mbstring \
    php8.2-curl \
    php8.2-zip \
    php8.2-bcmath \
    php8.2-gd \
    php8.2-intl

# Verificar instalación
php -v
# Debe mostrar: PHP 8.2.x

# Iniciar PHP-FPM
sudo systemctl start php8.2-fpm
sudo systemctl enable php8.2-fpm
```

### 3.6 Instalar Composer

```bash
cd ~
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verificar
composer --version
```

### 3.7 Instalar Git

```bash
sudo apt install -y git

# Configurar Git (opcional)
git config --global user.name "Tu Nombre"
git config --global user.email "tu@email.com"
```

---

## Paso 4: Configurar Directorio del Proyecto

### 4.1 Crear Estructura

```bash
# Crear directorio web
sudo mkdir -p /var/www/seo-automation

# Cambiar propietario a deploy
sudo chown -R deploy:deploy /var/www/seo-automation
```

### 4.2 Subir Código del Proyecto

**Opción A: Git Clone (Recomendado)**

Si tienes el código en GitHub/GitLab:

```bash
cd /var/www/seo-automation
git clone https://github.com/TU_USUARIO/seo-automation.git .

# O si es privado:
git clone git@github.com:TU_USUARIO/seo-automation.git .
```

**Opción B: SCP desde Local**

Desde tu máquina local (WSL):

```bash
cd ~/proyectos/amaw/seo-automation/seo-automation

# Comprimir proyecto (excluyendo vendor, node_modules, etc.)
tar --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='storage/logs/*' \
    --exclude='database/database.sqlite' \
    -czf seo-automation.tar.gz .

# Subir al servidor
scp seo-automation.tar.gz deploy@TU_IP_DROPLET:/var/www/seo-automation/

# En el servidor, descomprimir
ssh deploy@TU_IP_DROPLET
cd /var/www/seo-automation
tar -xzf seo-automation.tar.gz
rm seo-automation.tar.gz
```

### 4.3 Instalar Dependencias

```bash
cd /var/www/seo-automation

# Instalar dependencias de Composer
composer install --no-dev --optimize-autoloader

# Si hay dependencias de NPM (opcional)
# npm install && npm run build
```

### 4.4 Configurar Variables de Entorno

```bash
cd /var/www/seo-automation

# Copiar .env de ejemplo
cp .env.example .env

# Editar .env
nano .env
```

**Configurar estas variables** en `.env`:

```env
APP_NAME="SEO Automation"
APP_ENV=production
APP_KEY=  # Se genera después
APP_DEBUG=false
APP_URL=http://TU_IP_DROPLET  # O tu dominio

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=seo_automation
DB_USERNAME=seo_user
DB_PASSWORD=TU_PASSWORD_MYSQL

# Rutas a datos SEMRush (ajustar según tu estructura)
SEO_DATA_PATH=/var/www/seo-automation/storage/semrush-data
```

**Guardar**: `Ctrl+O`, `Enter`, `Ctrl+X`

### 4.5 Generar App Key

```bash
cd /var/www/seo-automation
php artisan key:generate
```

Esto actualizará automáticamente `APP_KEY` en `.env`.

### 4.6 Configurar Permisos

```bash
cd /var/www/seo-automation

# Storage y bootstrap/cache deben ser escribibles
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## Paso 5: Ejecutar Migrations y Seeders

### 5.1 Verificar Conexión MySQL

```bash
cd /var/www/seo-automation
php artisan tinker --execute="echo 'DB: ' . config('database.default');"

# Debe mostrar: DB: mysql
```

### 5.2 Ejecutar Migrations

```bash
cd /var/www/seo-automation

# IMPORTANTE: Esto creará todas las tablas y aplicará el PARTICIONAMIENTO
php artisan migrate --force

# Verificar
php artisan migrate:status
```

**Resultado esperado**:
- 8 migrations ejecutadas ✅
- Particionamiento aplicado en `keyword_rankings` ✅

### 5.3 Ejecutar Seeders

```bash
cd /var/www/seo-automation

# Seeders de catálogos
php artisan db:seed --class=CatalogSeeder --force
php artisan db:seed --class=CitiesSeeder --force
```

**Verificar**:
```bash
php artisan tinker --execute="
echo 'Cities: ' . \App\Models\City::count() . PHP_EOL;
echo 'Search Intents: ' . \App\Models\SearchIntent::count() . PHP_EOL;
"

# Esperado:
# Cities: 19
# Search Intents: 5
```

---

## Paso 6: Configurar Nginx

### 6.1 Crear Virtual Host

```bash
sudo nano /etc/nginx/sites-available/seo-automation
```

**Pegar esta configuración**:

```nginx
server {
    listen 80;
    listen [::]:80;

    server_name TU_IP_DROPLET;  # O tu dominio: seo.alquilatucarro.com.co

    root /var/www/seo-automation/public;
    index index.php index.html;

    # Logs
    access_log /var/log/nginx/seo-automation-access.log;
    error_log /var/log/nginx/seo-automation-error.log;

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to .htaccess
    location ~ /\.ht {
        deny all;
    }

    # Deny access to sensitive files
    location ~ /\.(env|git) {
        deny all;
    }
}
```

**Guardar**: `Ctrl+O`, `Enter`, `Ctrl+X`

### 6.2 Habilitar Virtual Host

```bash
# Crear symlink
sudo ln -s /etc/nginx/sites-available/seo-automation /etc/nginx/sites-enabled/

# Eliminar default (opcional)
sudo rm /etc/nginx/sites-enabled/default

# Test configuración
sudo nginx -t

# Debe mostrar:
# nginx: configuration file /etc/nginx/nginx.conf test is successful

# Reload Nginx
sudo systemctl reload nginx
```

---

## Paso 7: Optimizar Laravel para Producción

### 7.1 Cachear Configuración

```bash
cd /var/www/seo-automation

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 7.2 Optimizar Autoloader

```bash
cd /var/www/seo-automation
composer dump-autoload --optimize --no-dev
```

---

## Paso 8: Configurar Firewall (UFW)

```bash
# Habilitar UFW
sudo ufw enable

# Permitir SSH
sudo ufw allow OpenSSH

# Permitir HTTP/HTTPS
sudo ufw allow 'Nginx Full'

# Verificar status
sudo ufw status

# Debe mostrar:
# Status: active
# To                         Action      From
# --                         ------      ----
# OpenSSH                    ALLOW       Anywhere
# Nginx Full                 ALLOW       Anywhere
```

---

## Paso 9: Verificar Deploy

### 9.1 Verificar Acceso Web

Abrir navegador en:
```
http://TU_IP_DROPLET
```

**Deberías ver**:
- Página de Laravel (si tienes rutas configuradas)
- O un error 404 (si no hay rutas en `routes/web.php`)

### 9.2 Verificar Base de Datos

```bash
cd /var/www/seo-automation

php artisan tinker --execute="
echo 'Domains: ' . \App\Models\Domain::count() . PHP_EOL;
echo 'Keywords: ' . \App\Models\Keyword::count() . PHP_EOL;
echo 'Cities: ' . \App\Models\City::count() . PHP_EOL;
"

# Esperado (si no has importado datos):
# Domains: 0
# Keywords: 0
# Cities: 19
```

### 9.3 Verificar Particionamiento MySQL

```bash
# Conectar a MySQL
mysql -u seo_user -p seo_automation

# Dentro de MySQL:
SELECT
    PARTITION_NAME,
    TABLE_ROWS
FROM information_schema.PARTITIONS
WHERE TABLE_NAME = 'keyword_rankings'
AND TABLE_SCHEMA = 'seo_automation';

# Deberías ver:
# +-----------------+------------+
# | PARTITION_NAME  | TABLE_ROWS |
# +-----------------+------------+
# | p_2026_01      |          0 |
# | p_2026_02      |          0 |
# | ...            |        ... |
# | p_future       |          0 |
# +-----------------+------------+

EXIT;
```

---

## Paso 10: Subir Datos de SEMRush

### 10.1 Crear Directorio de Datos

```bash
cd /var/www/seo-automation
mkdir -p storage/semrush-data
```

### 10.2 Subir Archivos desde Local

Desde tu máquina local (WSL):

```bash
cd ~/proyectos/amaw/seo-automation

# Comprimir datos de SEMRush
tar -czf semrushdiego.tar.gz semrushdiego/

# Subir al servidor
scp semrushdiego.tar.gz deploy@TU_IP_DROPLET:/var/www/seo-automation/storage/semrush-data/

# En el servidor, descomprimir
ssh deploy@TU_IP_DROPLET
cd /var/www/seo-automation/storage/semrush-data
tar -xzf semrushdiego.tar.gz
rm semrushdiego.tar.gz
```

### 10.3 Actualizar config/seo.php

```bash
cd /var/www/seo-automation
nano config/seo.php
```

**Actualizar la ruta**:
```php
'data_path' => env('SEO_DATA_PATH', storage_path('semrush-data/semrushdiego')),
```

---

## Paso 11: Importar Datos

### 11.1 Importar Dominios

```bash
cd /var/www/seo-automation
php artisan seo:import:domains

# Esperado:
# 16 dominios importados ✅
```

### 11.2 Importar Keywords

```bash
cd /var/www/seo-automation
php artisan seo:import:keywords --source=storage/semrush-data/semrushdiego/keywords

# Esto puede tardar 5-10 minutos
# Esperado: ~50K keywords importadas
```

### 11.3 Importar Rankings

```bash
cd /var/www/seo-automation
php artisan seo:import:rankings --type=own

# Esperado: ~20K rankings importados
```

### 11.4 Verificar Particionamiento con Datos

```bash
mysql -u seo_user -p seo_automation

# Dentro de MySQL:
SELECT
    PARTITION_NAME,
    TABLE_ROWS
FROM information_schema.PARTITIONS
WHERE TABLE_NAME = 'keyword_rankings'
AND TABLE_SCHEMA = 'seo_automation';

# Deberías ver datos distribuidos por partición:
# +-----------------+------------+
# | PARTITION_NAME  | TABLE_ROWS |
# +-----------------+------------+
# | p_2026_01      |      15000 |
# | p_2026_02      |       6977 |
# | p_2026_03      |          0 |
# | ...            |        ... |
# +-----------------+------------+

EXIT;
```

---

## Paso 12: Configurar SSL (Opcional pero Recomendado)

### 12.1 Instalar Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
```

### 12.2 Obtener Certificado

**Solo si tienes un dominio configurado** (ej: seo.alquilatucarro.com.co):

```bash
sudo certbot --nginx -d seo.alquilatucarro.com.co

# Responder:
# - Email: tu@email.com
# - Terms of Service: A (agree)
# - Share email: N (no)
# - Redirect HTTP to HTTPS: 2 (sí)
```

Certbot configurará automáticamente Nginx con SSL y renovación automática.

---

## Paso 13: Configurar Cron Jobs (Opcional)

Para actualizaciones automáticas mensuales:

```bash
crontab -e

# Agregar línea (actualización el día 1 de cada mes a las 2 AM):
0 2 1 * * cd /var/www/seo-automation && php artisan seo:import:keywords --source=storage/semrush-data/semrushdiego/keywords >> /var/log/seo-cron.log 2>&1
```

---

## 🎉 Deploy Completado

### Resumen de URLs

- **HTTP**: `http://TU_IP_DROPLET`
- **HTTPS**: `https://seo.alquilatucarro.com.co` (si configuraste SSL)
- **SSH**: `ssh deploy@TU_IP_DROPLET`

### Resumen de Credenciales

**MySQL**:
- Host: `localhost`
- Database: `seo_automation`
- Usuario: `seo_user`
- Password: `[TU_PASSWORD_MYSQL]`

**Sistema**:
- Usuario: `deploy`
- Directorio: `/var/www/seo-automation`
- Logs Nginx: `/var/log/nginx/seo-automation-*.log`
- Logs Laravel: `/var/www/seo-automation/storage/logs/laravel.log`

### Datos Importados

- ✅ 16 dominios
- ✅ ~50K keywords
- ✅ ~20K rankings
- ✅ Particionamiento activo (MySQL)

---

## 🔧 Comandos Útiles

### Ver Logs

```bash
# Logs de Nginx
sudo tail -f /var/log/nginx/seo-automation-error.log

# Logs de Laravel
tail -f /var/www/seo-automation/storage/logs/laravel.log

# Logs de PHP-FPM
sudo tail -f /var/log/php8.2-fpm.log
```

### Reiniciar Servicios

```bash
# Nginx
sudo systemctl restart nginx

# PHP-FPM
sudo systemctl restart php8.2-fpm

# MySQL
sudo systemctl restart mysql
```

### Actualizar Código

```bash
cd /var/www/seo-automation

# Si usas Git
git pull origin main

# Instalar dependencias nuevas
composer install --no-dev --optimize-autoloader

# Ejecutar nuevas migrations
php artisan migrate --force

# Limpiar caché
php artisan config:clear
php artisan cache:clear

# Recrear caché
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 🚨 Troubleshooting

### Error: "500 Internal Server Error"

```bash
# Verificar permisos
cd /var/www/seo-automation
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Ver logs
tail -f storage/logs/laravel.log
```

### Error: "Access denied for user 'seo_user'"

```bash
# Verificar credenciales en .env
cat .env | grep DB_

# Testear conexión manual
mysql -u seo_user -p seo_automation
```

### Error: "Class not found"

```bash
cd /var/www/seo-automation
composer dump-autoload --optimize
php artisan config:clear
```

---

**¿Dudas?** Consulta logs o abre issue en repositorio.
