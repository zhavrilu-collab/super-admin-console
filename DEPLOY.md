# Deploy checklist — Super-Admin konzola + Udruga SaaS

Checklist za produkcijski deploy oba sustava koji rade zajedno.

## Arhitektura

| Aplikacija | Uloga | Tipični port (dev) |
|---|---|---|
| **multi-tenant console** | Super-Admin metadata, moderacija, sync | 8001 |
| **udruga-saas** | Tenant aplikacija (udruga) | 8000 |

**Integracija:**
- Console → SaaS: `GET/PATCH /api/admin/organizations` (Bearer `ADMIN_SYNC_API_KEY`)
- SaaS → Console: `POST /api/webhooks/tenants/registered` (Bearer webhook secret)

---

## 1. Prije deploya

- [ ] PHP 8.2+ s ekstenzijama: `openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- [ ] Composer instaliran na serveru
- [ ] MySQL/MariaDB za udruga-saas; SQLite ili MySQL za konzolu
- [ ] HTTPS certifikat (Let's Encrypt / reverse proxy)
- [ ] DNS za obje domene (npr. `admin.example.hr`, `app.example.hr`)
- [ ] Zajednički tajni ključevi generirani (`openssl rand -hex 32`)

---

## 2. Super-Admin konzola

```bash
cd /var/www/admin-console
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=AdminConsoleSeeder   # samo prvi put / staging
```

### `.env` (produkcija)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin.example.hr
APP_FORCE_HTTPS=true

DB_CONNECTION=sqlite   # ili mysql
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
QUEUE_CONNECTION=database
CACHE_STORE=database

TRUSTED_PROXIES=*

UDRUGA_SAAS_API_URL=https://app.example.hr
UDRUGA_SAAS_API_KEY=<isti kao ADMIN_SYNC_API_KEY na SaaS-u>
SAAS_WEBHOOK_SECRET=<isti kao ADMIN_CONSOLE_WEBHOOK_SECRET na SaaS-u>

# Same-VPS behind SSL-inspecting firewall / hairpin NAT:
SAAS_HTTP_VERIFY=false
SAAS_HTTP_RESOLVE_LOOPBACK=true
```

### Post-deploy

- [ ] U `/admin/postavke` postavi SMTP (From adresa, host, port)
- [ ] U `/admin/aplikacije` provjeri API URL i sync ključ za `udruga-saas`
- [ ] Super-admin uključi **2FA** na `/admin/sigurnost`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`

### Cron (obavezno)

```cron
* * * * * cd /var/www/admin-console && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler pokreće:
- `tenants:sync` — svaki sat
- `queue:work` — svake minute (e-mail obavijesti)

**Preporuka za veći promet:** Supervisor umjesto schedule queue-a:

```ini
[program:admin-queue]
command=php /var/www/admin-console/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
```

### Nginx (primjer)

```nginx
server {
    listen 443 ssl http2;
    server_name admin.example.hr;
    root /var/www/admin-console/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

---

## 3. Udruga SaaS

```bash
cd /var/www/udruga-saas
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
```

### `.env` (produkcija)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.hr
APP_FORCE_HTTPS=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=udruga_saas
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
QUEUE_CONNECTION=database
CACHE_STORE=database

TRUSTED_PROXIES=*

ADMIN_SYNC_API_KEY=<zajednički ključ>
ADMIN_CONSOLE_WEBHOOK_URL=https://admin.example.hr/api/webhooks/tenants/registered
ADMIN_CONSOLE_WEBHOOK_SECRET=<zajednički webhook secret>
ADMIN_CONSOLE_APPLICATION_SLUG=udruga-saas
ADMIN_CONSOLE_API_URL=https://admin.example.hr

# Same-VPS behind SSL-inspecting firewall / hairpin NAT:
ADMIN_CONSOLE_HTTP_VERIFY=false
ADMIN_CONSOLE_HTTP_RESOLVE_LOOPBACK=true
```

### Post-deploy

- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] `storage/` i `bootstrap/cache/` writable za `www-data`

### Cron

```cron
* * * * * cd /var/www/udruga-saas && php artisan schedule:run >> /dev/null 2>&1
```

---

## 4. Provjera integracije

1. [ ] `curl https://app.example.hr/up` → 200
2. [ ] `curl https://admin.example.hr/up` → 200
3. [ ] Registracija nove udruge na SaaS-u → tenant `pending` u konzoli
4. [ ] Super-admin dobije e-mail obavijest (ili provjeri `storage/logs/laravel.log` ako je mailer `log`)
5. [ ] Odobrenje tenanta u konzoli → status `active` u SaaS-u (`PATCH` sync)
6. [ ] Ručni sync na dashboardu → `last_synced_at` ažuriran
7. [ ] Audit log bilježi moderacijske akcije

### Test API sync-a (ručno)

```bash
curl -H "Authorization: Bearer <ADMIN_SYNC_API_KEY>" \
  https://app.example.hr/api/admin/organizations
```

### Test webhooka (ručno)

```bash
curl -X POST -H "Authorization: Bearer <WEBHOOK_SECRET>" \
  -H "Content-Type: application/json" \
  -d '{"application_slug":"udruga-saas","organization":{"id":1,"name":"Test","slug":"test","status":"pending","plan":"free"}}' \
  https://admin.example.hr/api/webhooks/tenants/registered
```

---

## 5. Sigurnost (oba sustava)

- [ ] `APP_DEBUG=false`
- [ ] `APP_FORCE_HTTPS=true` + `TRUSTED_PROXIES=*` iza nginx/Cloudflare
- [ ] Jedinstveni jaki API/webhook ključevi (ne `dev-sync-key-change-me`)
- [ ] 2FA uključen za sve super-admine
- [ ] Firewall: admin sync API dostupan samo s IP konzole (opcionalno)
- [ ] Redoviti backup baze (`tenants`, `organizations`, `settings`)
- [ ] `failed_jobs` tablica praćena nakon deploya

---

## 6. Rollback

```bash
php artisan migrate:rollback --step=1   # oprezno na produkciji
git checkout <prethodni-tag>
composer install --no-dev
php artisan config:cache
php artisan queue:restart
```

---

## 7. Lokalni dev (Windows / XAMPP)

```powershell
# Terminal 1 — udruga-saas
cd C:\Users\zoran.havriluk\udruga-siletici\udruga-saas
C:\xampp\php\php.exe artisan serve --port=8000

# Terminal 2 — admin konzola
cd "C:\Users\zoran.havriluk\multi-tenant console"
C:\xampp\php\php.exe artisan serve --port=8001
```

Zajednički dev ključevi u `.env` oba projekta: `dev-sync-key-change-me`

---

## 8. Docker i Supervisor

Kompletna konfiguracija u mapi **[deploy/](deploy/README.md)**:

| Datoteka | Svrha |
|---|---|
| `deploy/docker-compose.yml` | nginx + PHP-FPM + MySQL + queue + scheduler |
| `deploy/supervisor/admin-console.conf` | Queue/scheduler na serveru (bez Dockera) |
| `deploy/supervisor/udruga-saas.conf` | Queue/scheduler za SaaS |
| `deploy/scripts/bootstrap.ps1` | Brzi start na Windows (Docker Desktop) |
| `deploy/scripts/bootstrap.sh` | Brzi start na Linux/macOS/WSL |

```powershell
cd "C:\Users\zoran.havriluk\multi-tenant console\deploy"
copy .env.docker.example .env.docker
.\scripts\bootstrap.ps1
```

Docker portovi: admin **8001**, SaaS **8000**.

### Ako nemaš Docker

`docker` nije prepoznat = Docker Desktop nije instaliran. Za lokalni razvoj koristi XAMPP:

```powershell
.\deploy\scripts\windows-xampp-workers.ps1
```

Vidi [deploy/README.md](deploy/README.md) sekciju **Windows (bez Dockera)**.
