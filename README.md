# MyTree

MyTree is the primary web application for the MyTree genealogical research system.

This repository is intentionally at a very early `0.0.x` stage. Database schema, application contracts and deployment details may change without backward compatibility.

## Current scope

This first application-foundation milestone contains:

- Laravel 13,
- Filament 5,
- PHP 8.4 FPM,
- Nginx,
- PostgreSQL,
- Redis,
- a Laravel queue worker,
- a Laravel scheduler,
- Docker Compose development/runtime infrastructure,
- Docker-first developer operations scripts under `ops/`.

It does **not** yet implement Source Acquisition, provider integration, application Settings, Engine integration or the final authentication workflow.

## Host requirements

Normal local development requires only:

- Git,
- Docker Engine,
- Docker Compose v2,
- Bash.

Host PHP, Composer and Node.js are not required.

The current user must be able to access the Docker daemon. The repository does not invoke `sudo`.

You can verify Docker access with:

```bash
docker info
docker compose version
```

If `docker info` works only with `sudo`, configure Docker access for your normal user before running the MyTree operations scripts.

## First local bootstrap

From a fresh clone, run:

```bash
./ops/setup.sh
```

The setup script:

1. validates Docker and Docker Compose v2,
2. verifies that the current user can access Docker without `sudo`,
3. creates `.env` from `.env.example` only when `.env` is missing,
4. builds the application image using the current host UID/GID,
5. installs Composer dependencies inside a container,
6. generates `APP_KEY` only when it is not already configured,
7. starts PostgreSQL and Redis,
8. runs Laravel migrations,
9. starts the complete application stack.

Re-running `./ops/setup.sh` keeps an existing `.env`, application key and persistent Docker volumes intact.

Open:

```text
http://localhost:8080/admin
```

The HTTP port can be changed with `HTTP_PORT` in `.env`.

The complete administrator authentication workflow is intentionally deferred to the dedicated authentication milestone.

## Developer operations

Start the stack:

```bash
./ops/start.sh
```

Stop the stack while preserving PostgreSQL/Redis data:

```bash
./ops/stop.sh
```

Show Compose state, container health, Laravel HTTP reachability and basic Laravel/Filament information:

```bash
./ops/status.sh
```

Show application, Nginx, worker and scheduler logs:

```bash
./ops/logs.sh
```

Follow all application logs:

```bash
./ops/logs.sh --follow
```

Inspect selected logical targets without knowing their Compose service names:

```bash
./ops/logs.sh app web
./ops/logs.sh --tail 250 worker scheduler
```

Run `./ops/logs.sh --help` for the complete logging command syntax.

The full automated quality-suite entry point is intentionally owned by the follow-up quality-gates work rather than duplicated here.

## Direct Compose commands

The `ops/` scripts are the normal developer entry points. Direct Compose commands remain useful for debugging:

```bash
docker compose ps
docker compose exec app php artisan about
docker compose exec app php artisan migrate:status
```

Remove containers and the Compose network while preserving named volumes:

```bash
docker compose down
```

Delete local persistent Docker volumes only when a destructive reset is intended:

```bash
docker compose down -v
```

## Persistence

PostgreSQL uses the named `postgres-data` volume.

Redis uses the named `redis-data` volume, but Redis is infrastructure for cache, queue and ephemeral coordination. It must never become the only copy of authoritative MyTree data.

## Docker health checks

Health checks intentionally verify live services:

- PostgreSQL uses `pg_isready`.
- Redis uses `redis-cli ping`.
- PHP-FPM is checked through a real FastCGI request to Laravel's `/up` endpoint.
- Nginx exposes an internal `/nginx-health` probe.

`./ops/status.sh` reports those real container health states and separately probes Laravel's `/up` endpoint through Nginx. A syntax-only configuration check is not treated as application health.

The Nginx FastCGI configuration uses Docker's embedded DNS resolver so recreation of the PHP application container does not leave Nginx pinned to a stale container IP.

## Repository rules

Canonical project architecture and documentation live in [`mytree-project/mytree-project`](https://github.com/mytree-project/mytree-project).

Changes to this repository follow the MyTree branch + pull-request workflow. The `main` branch is not a direct-write target for AI-assisted changes.
