# Docker + Supervisor — produkcijski stack

Lokalni Docker stack za **Super-Admin konzolu** i **udruga-saas**, uključujući queue workere i scheduler.

## Struktura

```
deploy/
├── docker-compose.yml      # nginx, php-fpm, mysql, queue, scheduler
├── docker/Dockerfile       # PHP 8.2 FPM
├── nginx/                  # virtual hostovi
├── supervisor/             # bare-metal Supervisor (bez Dockera)
├── scripts/bootstrap.sh    # prvi setup
└── .env.docker.example
```

## Brzi start (Linux / WSL / macOS)

```bash
cd deploy
cp .env.docker.example .env.docker
# Prilagodi ADMIN_APP_PATH / SAAS_APP_PATH ako treba

chmod +x scripts/bootstrap.sh
./scripts/bootstrap.sh
```

Ručno:

```bash
cd deploy
cp .env.docker.example .env.docker
docker compose --env-file .env.docker up -d --build
```

| Servis | URL |
|---|---|
| Admin konzola | http://localhost:8001 |
| Udruga SaaS | http://localhost:8000 |

## Servisi u Compose-u

| Container | Uloga |
|---|---|
| `mysql` | Baza za udruga-saas |
| `admin-php` / `saas-php` | PHP-FPM |
| `nginx` | Reverse proxy prema FPM-u |
| `admin-queue` / `saas-queue` | `queue:work` (webhook, e-mail) |
| `admin-scheduler` / `saas-scheduler` | `schedule:run` svake minute |

Admin konzola u Dockeru koristi **SQLite** (volume na `database/database.sqlite`).  
Za produkcijski MySQL na konzoli zamijeni `DB_*` u `docker-compose.yml`.

## Mreža između kontejnera

Unutar Docker mreže:
- SaaS webhook → `http://nginx/api/webhooks/tenants/registered` (admin API na portu 80 unutar nginx kontejnera)
- Console sync → `http://nginx:8080` (SaaS API)

Ključevi: `SYNC_API_KEY` i `WEBHOOK_SECRET` u `.env.docker`.

## Supervisor na bare-metal serveru

Ako ne koristiš Docker queue kontejnere, kopiraj u `/etc/supervisor/conf.d/`:

```bash
sudo cp supervisor/admin-console.conf /etc/supervisor/conf.d/
sudo cp supervisor/udruga-saas.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Prilagodi putanje (`/var/www/admin-console`, `/var/www/udruga-saas`) i korisnika `www-data`.

Alternativa scheduleru: cron `* * * * * php artisan schedule:run` (vidi `DEPLOY.md`).

## Produkcija iza HTTPS

1. Postavi vanjski nginx/Caddy s TLS ispred Docker portova **ili** mapiraj samo na `127.0.0.1`.
2. U `.env` aplikacija: `APP_FORCE_HTTPS=true`, `TRUSTED_PROXIES=*`.
3. Promijeni sve lozinke i API ključeve iz `.env.docker.example`.

## Korisne naredbe

```bash
docker compose --env-file .env.docker logs -f admin-queue
docker compose --env-file .env.docker exec admin-php php artisan tenants:sync
docker compose --env-file .env.docker down
docker compose --env-file .env.docker down -v   # briše MySQL volume
```

## Windows (bez Dockera)

Docker zahtijeva **Docker Desktop**. Ako nije instaliran, koristi **XAMPP** kao dosad:

```powershell
# Terminal 1 — udruga-saas
cd C:\Users\zoran.havriluk\udruga-siletici\udruga-saas
C:\xampp\php\php.exe artisan serve --port=8000

# Terminal 2 — admin konzola
cd "C:\Users\zoran.havriluk\multi-tenant console"
C:\xampp\php\php.exe artisan serve --port=8001
```

Queue + scheduler (webhook e-mail, `tenants:sync`):

```powershell
cd "C:\Users\zoran.havriluk\multi-tenant console\deploy"
.\scripts\windows-xampp-workers.ps1
```

Otvorit će se 4 minimizirana PowerShell prozora (queue + scheduler za oba appa).  
Za zaustavljanje ih zatvori ručno.

Ručno (jedan worker):

```powershell
cd "C:\Users\zoran.havriluk\multi-tenant console"
C:\xampp\php\php.exe artisan queue:work
```

### Docker Desktop (opcionalno)

Instaliraj [Docker Desktop](https://www.docker.com/products/docker-desktop/), restartaj PowerShell, zatim:

```powershell
cd "C:\Users\zoran.havriluk\multi-tenant console\deploy"
copy .env.docker.example .env.docker
.\scripts\bootstrap.ps1
```
