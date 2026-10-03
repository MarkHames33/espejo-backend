# LavaLust Product Management API  (Laboratory Exercise No. 6)

REST API built with the **LavaLust** framework.
Stack: **LavaLust API** + **Aiven MySQL** + **Render** (frontend: React, see `products-frontend`).

## Endpoints

| Method | URL | Auth | Description |
|---|---|---|---|
| GET | `/api` | no | API info / health check |
| POST | `/api/auth/register` | no | `{username,email,password}` |
| POST | `/api/auth/login` | no | `{login,password}` (login = email or username) |
| POST | `/api/auth/refresh` | no | `{refresh_token}` |
| POST | `/api/auth/logout` | no | `{refresh_token}` |
| GET | `/api/auth/me` | **Bearer** | current user |
| GET | `/api/products?search=` | **Bearer** | list products |
| GET | `/api/products/{id}` | **Bearer** | one product |
| POST | `/api/products` | **Bearer** | `{product_name,description,price,quantity}` |
| PUT / PATCH | `/api/products/{id}` | **Bearer** | update (PATCH = partial) |
| DELETE | `/api/products/{id}` | **Bearer** | delete |

Authentication = JWT access token (15 min) + refresh token (7 days, stored hashed in `refresh_tokens`),
using the LavaLust `Api` library. Send `Authorization: Bearer <access_token>`.

## Run locally

```bash
cp .env.example .env          # then edit DB_* values (local MySQL: DB_SSL=false)
php lava jwt:generate         # fills JWT_SECRET and REFRESH_TOKEN_KEY in .env
# set MIGRATION_ENABLED=true in .env
php lava migration run        # creates migrations, users, refresh_tokens, products
php lava serve                # http://127.0.0.1:3000   (try http://127.0.0.1:3000/api)
```

### Migration commands (from the lab)

```bash
php lava migration status
php lava migration create-migration create_something_table
php lava migration run
php lava migration rollback
php lava migration rollback-all     # development DB only!
php lava migration refresh          # development DB only!
```
Files: `app/commands/Migration.php` (custom CLI command), `app/controllers/MigrationController.php`,
migration routes in `app/config/routes.php`, migrations in `app/migrations/`.

> Note: in this LavaLust version custom CLI commands are auto-loaded from **`app/commands/`**
> (with an "s"), not `app/command/` as written in the activity sheet.

## Aiven MySQL

Option A - run migrations against Aiven: put the Aiven values in `.env`
(`DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`, `DB_SSL=true`), set `MIGRATION_ENABLED=true`,
then `php lava migration run`.

Option B - paste `database/aiven_setup.sql` into the Aiven query console / DBeaver / Workbench.
It creates all 4 tables, marks the migrations as applied, and adds a demo account
`admin@example.com` / `Admin@12345` plus 3 sample products. **Change that password afterwards.**

## Deploy to Render (Docker)

1. Push this folder to a GitHub repo (`.env` is git-ignored - never commit it).
2. Render -> **New + -> Web Service** -> pick the repo -> **Runtime: Docker**.
3. Add **Environment Variables**:

| Key | Value |
|---|---|
| `APP_ENV` | `production` |
| `DB_DRIVER` | `mysql` |
| `DB_HOST` / `DB_PORT` / `DB_USER` / `DB_PASSWORD` / `DB_NAME` | from Aiven "Connection information" |
| `DB_CHARSET` | `utf8mb4` |
| `DB_SSL` | `true` |
| `DB_SSL_CA_CONTENT` | (optional) full text of Aiven `ca.pem` |
| `JWT_SECRET` | 64+ random chars (`php lava jwt:generate --show`) |
| `REFRESH_TOKEN_KEY` | another 64+ random chars, different from JWT_SECRET |
| `MIGRATION_ENABLED` | `false` |
| `CORS_ORIGIN` | your frontend URL, e.g. `https://your-frontend.onrender.com` (comma-separate several) |

4. Deploy, then open `https://<your-service>.onrender.com/api` - you should see the JSON status.
5. In Aiven, if "Allowed IP addresses" is restricted, allow `0.0.0.0/0` (or Render's outbound IPs).

## What was changed in the framework

* `app/config/api.php` - API enabled; CORS origin and issuer set; secrets read from environment.
* `app/config/database.php` + `scheme/database/Database.php` - optional SSL (`DB_SSL`, `DB_SSL_CA`, `DB_SSL_CA_CONTENT`) because Aiven requires it.
* `app/config/migration.php` - `migration_enabled` is controlled by `MIGRATION_ENABLED`.
* `scheme/kernel/Routine.php` - preflight (OPTIONS) requests now load the API config so browsers get the CORS headers.
* `Dockerfile` - Apache listens on Render's `PORT`.
