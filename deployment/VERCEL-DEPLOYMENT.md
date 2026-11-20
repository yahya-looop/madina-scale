# MADINA Scale — Vercel multi-service deployment

This deployment setup is intentionally additive. It adds only:

- `vercel.json`
- `Dockerfile.vercel`
- `.dockerignore`
- this deployment guide

It does not replace or edit the existing Laravel, Inertia, Vite, Nuxt, database, or payment application files.

## Services

- `storefront`: Nuxt application rooted at `storefront/`, served at `/`.
- `backend`: Laravel 13 / PHP 8.3 Apache container rooted at the repository root.
- `/api/*`, `/backend*`, `/build/*`, `/storage/*`, `/robots.txt`, `/sitemap.xml`, and `/up` are routed to Laravel.
- Other paths are routed to Nuxt.

The Laravel container builds the existing root Vite assets during image build. The Nuxt service builds independently from `storefront/`.

## Important: preview first

Vercel Services and Container Runtime are beta features. Use a Preview deployment first. Do not point production DNS or real customers at this deployment until the checks below pass.

## Required Vercel project setup

1. Import the repository `yahya-looop/madina-scale`.
2. Select the branch `vercel-deployment-add-only` for the first Preview deployment.
3. Set Framework Preset to **Services**.
4. Keep the project Root Directory at `./`.
5. Confirm Vercel detects the `backend` and `storefront` services from the root `vercel.json`.
6. Add environment variables to the Vercel project. Never commit secrets to Git.

## Laravel environment variables

Set these in Vercel for the Preview environment using values from a dedicated staging environment:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` — generate a dedicated key for this deployment; do not reuse or expose a local secret.
- `APP_URL` — the Vercel deployment URL.
- `LOG_CHANNEL=stderr`
- `DB_CONNECTION=mysql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — a managed, persistent MySQL database.
- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=database` only when a separate queue worker is configured; otherwise use a deliberately chosen supported queue strategy for preview.
- `SESSION_SECURE_COOKIE=true`
- `SESSION_SAME_SITE=lax`
- `FILESYSTEM_DISK=s3` when the application stores customer uploads or product media. Configure the existing S3-compatible variables (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, and, if applicable, `AWS_ENDPOINT` / `AWS_URL`).

Use a separate staging database and storage bucket. Do not connect a first Preview deployment to the live production database.

## Database and persistent services

Vercel containers are not a persistent database or file store. The Laravel application's current local defaults include SQLite, database-backed sessions/cache/queue, and local file storage. For a real deployment:

- Use a managed MySQL database reachable from Vercel.
- Run migrations manually against the staging database after reviewing the migration plan. This deployment does not run migrations automatically.
- Confirm the `sessions`, `cache`, `jobs`, and other required tables exist if their database drivers are selected.
- Use S3-compatible object storage for persistent uploads.
- Configure and operate a separate queue worker before relying on queued jobs. A request-serving container is not a continuously running queue worker.

## Nuxt API routing

The existing Nuxt configuration defaults its API base to the same-origin `/api/v1/storefront`. Vercel's top-level rewrites route `/api/*` directly to the Laravel service before the Nuxt service handles the request. This avoids relying on the local `127.0.0.1:8001` proxy in production without editing the existing Nuxt config.

## Preview acceptance checklist

- `/ ` loads the Nuxt storefront.
- `/api/v1/storefront/products` responds from Laravel.
- `/backend/login` loads the Laravel staff login.
- `/up` responds with Laravel's health status.
- Inertia page assets under `/build/*` load successfully.
- Session and CSRF behavior work on the shared deployment domain.
- Product images and uploaded media load from persistent object storage.
- Checkout and payment workflows are tested only with staging data and non-production payment credentials.

Do not merge this branch into `main` or switch the production domain until the Preview acceptance checklist passes.
