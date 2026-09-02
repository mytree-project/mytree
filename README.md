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
- Docker Compose development/runtime infrastructure.

It does **not** yet implement Source Acquisition, provider integration, application Settings, Engine integration or the final authentication workflow.

## Host requirements

Normal local development requires only:

- Git,
- Docker Engine,
- Docker Compose v2.

Host PHP, Composer and Node.js are not required.

The current user must be able to access the Docker daemon. The repository does not invoke `sudo`.

## First local bootstrap

The dedicated `ops/setup.sh`, `ops/start.sh` and related developer scripts are tracked separately and will be added in the next infrastructure issue. Until then, bootstrap the foundation with containerized commands:

```bash
cp .env.example .env

# Use your host identity for bind-mounted runtime files.
export HOST_UID="$(id -u)"
export HOST_GID="$(id -g)"

docker compose build

docker compose run --rm app composer install --no-interaction --prefer-dist
docker compose run --rm app php artisan key:generate
docker compose up -d postgres redis
docker compose run --rm app php artisan migrate --force
docker compose up -d
```

Open:

```text
http://localhost:8080/admin
```

The HTTP port can be changed with `HTTP_PORT` in `.env`.

The complete administrator authentication workflow is intentionally deferred to the dedicated authentication milestone.

## Useful commands

```bash
docker compose ps
docker compose logs -f app nginx worker scheduler
docker compose exec app php artisan about
docker compose exec app php artisan migrate:status
```

Stop containers while preserving PostgreSQL/Redis volumes:

```bash
docker compose stop
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

The Nginx FastCGI configuration uses Docker's embedded DNS resolver so recreation of the PHP application container does not leave Nginx pinned to a stale container IP.

## Repository rules

Canonical project architecture and documentation live in [`mytree-project/mytree-project`](https://github.com/mytree-project/mytree-project).

Changes to this repository follow the MyTree branch + pull-request workflow. The `main` branch is not a direct-write target for AI-assisted changes.
