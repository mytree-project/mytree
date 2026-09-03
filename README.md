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
- Docker-first developer operations scripts under `ops/`,
- Filament administrator authentication and a baseline system dashboard,
- containerized PHPUnit, Laravel Pint and Larastan/PHPStan quality gates,
- GitHub Actions CI for pull requests and pushes to `main`,
- explicit repository-local application dependency and persistence boundaries.

It does **not** yet implement Source Acquisition, provider integration, application Settings or Engine integration.

## Application architecture

The application follows a pragmatic modular-monolith dependency direction:

```text
Filament / Console / Laravel composition roots
                  ↓
        Infrastructure / Adapters
                  ↓
        Application / Contracts
                  ↓
                 Domain
```

Only layers and capability directories required by real code should exist. Framework-independent Domain/Application code must not depend on Laravel, Filament or Eloquent; Eloquent persistence lives explicitly under `app/Infrastructure/Persistence/Eloquent`.

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the repository-local namespace, dependency, adapter and future capability conventions. Canonical project-wide architecture remains in `mytree-project/mytree-project`.

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

Create the first administrator after setup:

```bash
docker compose exec app php artisan mytree:admin create
```

The command interactively asks for the administrator email, display name and password. Password entry is hidden, requires confirmation and must contain at least 12 characters. There is no default administrator password in the repository.

To reset the password of an existing administrator:

```bash
docker compose exec app php artisan mytree:admin reset admin@example.test
```

The reset command also prompts for the new password without echoing it. It refuses to reset a regular non-administrator account.

Open:

```text
http://localhost:8080/admin
```

Unauthenticated requests are redirected to `/admin/login`. The HTTP port can be changed with `HTTP_PORT` in `.env`; keep `APP_URL` aligned with the URL used to reach MyTree.

After login, the baseline dashboard reports non-sensitive operational information only: application environment and URL, PHP/Laravel/Filament versions, database and Redis connectivity, and configured session/queue backends. It does not display environment dumps, credentials, tokens or connection passwords.

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

## Quality gates

Run the complete local quality suite with one Docker-first command:

```bash
./ops/test.sh
```

The command:

1. builds the application image,
2. installs exactly the Composer dependencies recorded in `composer.lock`,
3. verifies formatting with Laravel Pint in non-mutating `--test` mode,
4. runs Larastan/PHPStan at level 8 without a generated baseline or blanket ignores,
5. runs the PHPUnit suite.

No host PHP or Composer installation is required.

The test process overrides application infrastructure with an isolated deterministic test environment: SQLite `:memory:`, array-backed cache/session/mail, synchronous queues and a fixed non-production application key. Normal automated tests must not depend on live genealogy portals or other third-party services.

For debugging an individual stage, use:

```bash
./ops/test.sh install
./ops/test.sh style
./ops/test.sh static
./ops/test.sh tests
```

The stage-specific commands are also used by GitHub Actions so local and CI gates have the same implementation. CI runs for pull requests and pushes to `main`, installs locked dependencies and requires no repository secrets for the normal quality suite.

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

Redis uses the named `redis-data` volume, but Redis is infrastructure for cache, queue, sessions and ephemeral coordination. It must never become the only copy of authoritative MyTree data.

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
