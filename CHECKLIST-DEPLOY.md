# Checklist de Deploy a DigitalOcean

**Droplet IP**: _____________
**Usuario**: deploy
**Base de datos**: seo_automation

---

## ☑️ Paso 2: Configuración Inicial

- [ ] Actualizar sistema: `apt update && apt upgrade -y`
- [ ] Crear usuario deploy: `adduser deploy`
- [ ] Agregar a sudo: `usermod -aG sudo deploy`
- [ ] Configurar SSH keys para deploy
- [ ] Reconectar como deploy: `ssh deploy@IP`

## ☑️ Paso 3: Instalar Stack LEMP

### Nginx
- [ ] Instalar: `sudo apt install -y nginx`
- [ ] Verificar: `nginx -v`
- [ ] Iniciar: `sudo systemctl start nginx && sudo systemctl enable nginx`
- [ ] Probar en navegador: `http://IP` → Ver página Nginx ✅

### MySQL 8.0
- [ ] Instalar: `sudo apt install -y mysql-server`
- [ ] Configurar seguro: `sudo mysql_secure_installation`
  - Password validation: **1 (MEDIUM)**
  - Root password: **__________**
  - Remove anonymous users: **y**
  - Disallow root login remotely: **y**
  - Remove test database: **y**

### Base de Datos
- [ ] Conectar: `sudo mysql`
- [ ] Crear BD: `CREATE DATABASE seo_automation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
- [ ] Crear usuario: `CREATE USER 'seo_user'@'localhost' IDENTIFIED BY 'PASSWORD';`
- [ ] Grant permisos: `GRANT ALL PRIVILEGES ON seo_automation.* TO 'seo_user'@'localhost';`
- [ ] Flush: `FLUSH PRIVILEGES;`
- [ ] Verificar: `SHOW DATABASES;`
- [ ] Salir: `EXIT;`

**Credenciales anotadas**:
- Usuario: `seo_user`
- Password: `__________`

### PHP 8.2
- [ ] Agregar repo: `sudo add-apt-repository -y ppa:ondrej/php && sudo apt update`
- [ ] Instalar PHP + extensiones:
  ```bash
  sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql \
    php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip \
    php8.2-bcmath php8.2-gd php8.2-intl
  ```
- [ ] Verificar: `php -v` → Debe mostrar PHP 8.2.x
- [ ] Iniciar PHP-FPM: `sudo systemctl start php8.2-fpm && sudo systemctl enable php8.2-fpm`

### Composer & Git
- [ ] Instalar Composer:
  ```bash
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
  sudo chmod +x /usr/local/bin/composer
  ```
- [ ] Verificar: `composer --version`
- [ ] Instalar Git: `sudo apt install -y git`

---

## ☑️ Paso 4: Configurar Proyecto

### Crear Directorio
- [ ] Crear: `sudo mkdir -p /var/www/seo-automation`
- [ ] Cambiar owner: `sudo chown -R deploy:deploy /var/www/seo-automation`

### Subir Código

**Desde tu máquina local (WSL)**:
```bash
cd ~/proyectos/amaw/seo-automation/seo-automation

# Comprimir (excluyendo vendor, node_modules, etc.)
tar --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='storage/logs/*' \
    --exclude='database/database.sqlite' \
    -czf seo-automation.tar.gz .

# Subir
scp seo-automation.tar.gz deploy@IP:/var/www/seo-automation/
```

**En el servidor**:
```bash
cd /var/www/seo-automation
tar -xzf seo-automation.tar.gz
rm seo-automation.tar.gz
```

- [ ] Código subido y descomprimido

### Instalar Dependencias
- [ ] `cd /var/www/seo-automation`
- [ ] `composer install --no-dev --optimize-autoloader`

### Configurar .env
- [ ] `cp .env.example .env`
- [ ] `nano .env`

**Editar estas líneas**:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://TU_IP

DB_CONNECTION=mysql
DB_DATABASE=seo_automation
DB_USERNAME=seo_user
DB_PASSWORD=TU_PASSWORD_MYSQL

SEO_DATA_PATH=/var/www/seo-automation/storage/semrush-data
```

- [ ] Guardar: `Ctrl+O`, `Enter`, `Ctrl+X`
- [ ] Generar key: `php artisan key:generate`

### Permisos
- [ ] `sudo chown -R deploy:www-data storage bootstrap/cache`
- [ ] `sudo chmod -R 775 storage bootstrap/cache`

---

## ☑️ Paso 5: Ejecutar Migrations

- [ ] Verificar conexión: `php artisan tinker --execute="echo 'DB: ' . config('database.default');"`
  - Debe mostrar: `DB: mysql` ✅
- [ ] **Ejecutar migrations**: `php artisan migrate --force`
  - **IMPORTANTE**: Esto aplica el PARTICIONAMIENTO en MySQL ✅
- [ ] Verificar: `php artisan migrate:status`
  - Debe mostrar 8 migrations ejecutadas

### Seeders
- [ ] `php artisan db:seed --class=CatalogSeeder --force`
- [ ] `php artisan db:seed --class=CitiesSeeder --force`
- [ ] Verificar:
  ```bash
  php artisan tinker --execute="echo 'Cities: ' . \App\Models\City::count();"
  ```
  - Esperado: `Cities: 19` ✅

---

## ☑️ Paso 6: Configurar Nginx

### Crear Virtual Host
- [ ] `sudo nano /etc/nginx/sites-available/seo-automation`

**Pegar** (reemplazar `TU_IP` con tu IP real):
```nginx
server {
    listen 80;
    listen [::]:80;

    server_name TU_IP;

    root /var/www/seo-automation/public;
    index index.php index.html;

    access_log /var/log/nginx/seo-automation-access.log;
    error_log /var/log/nginx/seo-automation-error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~ /\.(env|git) {
        deny all;
    }
}
```

- [ ] Guardar: `Ctrl+O`, `Enter`, `Ctrl+X`

### Habilitar
- [ ] `sudo ln -s /etc/nginx/sites-available/seo-automation /etc/nginx/sites-enabled/`
- [ ] `sudo rm /etc/nginx/sites-enabled/default` (opcional)
- [ ] Test config: `sudo nginx -t`
  - Debe mostrar: `test is successful` ✅
- [ ] Reload: `sudo systemctl reload nginx`

---

## ☑️ Paso 7: Optimizar Laravel

- [ ] `cd /var/www/seo-automation`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] `composer dump-autoload --optimize --no-dev`

---

## ☑️ Paso 8: Firewall

- [ ] `sudo ufw enable`
- [ ] `sudo ufw allow OpenSSH`
- [ ] `sudo ufw allow 'Nginx Full'`
- [ ] Verificar: `sudo ufw status`

---

## ☑️ Paso 9: Verificar Deploy

### Acceso Web
- [ ] Abrir navegador: `http://TU_IP`
  - Deberías ver página de Laravel o 404 (normal si no hay rutas)

### Base de Datos
- [ ] Verificar:
  ```bash
  php artisan tinker --execute="
  echo 'Cities: ' . \App\Models\City::count() . PHP_EOL;
  "
  ```
  - Esperado: `Cities: 19` ✅

### Particionamiento MySQL
- [ ] Conectar: `mysql -u seo_user -p seo_automation`
- [ ] Verificar particiones:
  ```sql
  SELECT PARTITION_NAME, TABLE_ROWS
  FROM information_schema.PARTITIONS
  WHERE TABLE_NAME = 'keyword_rankings'
  AND TABLE_SCHEMA = 'seo_automation';
  ```
  - Deberías ver 13 particiones (p_2026_01 a p_2026_12 + p_future) ✅
- [ ] Salir: `EXIT;`

---

## ☑️ Paso 10: Subir Datos SEMRush

### Crear Directorio
- [ ] `mkdir -p /var/www/seo-automation/storage/semrush-data`

### Subir Archivos

**Desde tu máquina local**:
```bash
cd ~/proyectos/amaw/seo-automation

# Comprimir
tar -czf semrushdiego.tar.gz semrushdiego/

# Subir
scp semrushdiego.tar.gz deploy@IP:/var/www/seo-automation/storage/semrush-data/
```

**En el servidor**:
```bash
cd /var/www/seo-automation/storage/semrush-data
tar -xzf semrushdiego.tar.gz
rm semrushdiego.tar.gz
ls -la semrushdiego/
```

- [ ] Datos subidos y descomprimidos

---

## ☑️ Paso 11: Importar Datos

### Dominios
- [ ] `cd /var/www/seo-automation`
- [ ] `php artisan seo:import:domains`
  - Esperado: `16 dominios importados` ✅

### Keywords
- [ ] `php artisan seo:import:keywords --source=storage/semrush-data/semrushdiego/keywords`
  - Esto tarda 5-10 minutos
  - Esperado: `~50K keywords importadas` ✅

### Rankings
- [ ] `php artisan seo:import:rankings --type=own`
  - Esperado: `~20K rankings importados` ✅

### Verificar Particionamiento con Datos
- [ ] `mysql -u seo_user -p seo_automation`
- [ ] Verificar distribución:
  ```sql
  SELECT PARTITION_NAME, TABLE_ROWS
  FROM information_schema.PARTITIONS
  WHERE TABLE_NAME = 'keyword_rankings'
  AND TABLE_SCHEMA = 'seo_automation';
  ```
  - Deberías ver datos distribuidos en particiones ✅
- [ ] Salir: `EXIT;`

---

## 🎉 Deploy Completado

### URLs
- **HTTP**: `http://TU_IP`
- **SSH**: `ssh deploy@TU_IP`

### Credenciales
- **MySQL**: seo_user / [password anotado arriba]
- **Usuario**: deploy

### Datos Finales
- [ ] Verificar:
  ```bash
  php artisan tinker --execute="
  echo 'Domains: ' . \App\Models\Domain::count() . PHP_EOL;
  echo 'Keywords: ' . \App\Models\Keyword::count() . PHP_EOL;
  echo 'Rankings: ' . \App\Models\KeywordRanking::count() . PHP_EOL;
  "
  ```

**Esperado**:
```
Domains: 16
Keywords: ~50000
Rankings: ~20000
```

---

## 📝 Notas

- Logs Nginx: `/var/log/nginx/seo-automation-*.log`
- Logs Laravel: `/var/www/seo-automation/storage/logs/laravel.log`
- Ver logs en tiempo real: `tail -f /var/log/nginx/seo-automation-error.log`

¿Problemas? Consultar **GUIA-DEPLOY-DIGITALOCEAN.md** sección Troubleshooting.
