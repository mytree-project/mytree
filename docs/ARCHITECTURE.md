# MyTree Web Application Architecture

## Purpose

This document defines repository-local implementation conventions for the `mytree-project/mytree` Laravel + Filament application.

Canonical project-wide architecture remains in `mytree-project/mytree-project/docs`. This file does not redefine Source/Mention/Claim semantics, Engine semantics, provider contracts or other project-wide decisions. It translates the accepted MyTree architecture into concrete dependency and namespace rules for this repository.

## Dependency direction

The application is a pragmatic modular monolith. Dependencies point inward toward framework-independent code:

```text
Filament / Console / Laravel composition roots
                  ↓
        Infrastructure / Adapters
                  ↓
        Application / Contracts
                  ↓
                 Domain
```

The exact set of layers used by a capability depends on the code that actually exists. A capability must not receive empty directories, interfaces or abstractions merely to make this diagram visible in the filesystem.

The rules are:

1. `Domain` contains framework-independent domain concepts and value objects.
2. `Application` contains use cases, application DTOs and contracts needed by use cases or adapters.
3. `Infrastructure` contains Eloquent persistence, Laravel-specific services and adapters for external packages or processes.
4. Filament, Console commands and Laravel service providers are framework entry/composition points.
5. Dependencies must not point from `Domain` or `Application` outward to Laravel, Filament, Eloquent or concrete integration implementations.
6. Circular dependencies between capabilities or layers are not allowed.

## Namespace and directory conventions

Create a directory only when real code needs it.

### Domain

Framework-independent business concepts belong under:

```text
app/Domain/<Capability>/...
```

Domain code must not depend on:

- Laravel or Filament classes,
- Eloquent,
- facades,
- `app()`, `resolve()`, `config()` or `env()`,
- queues, HTTP clients, filesystem/storage APIs or other infrastructure,
- concrete provider/processor/Engine implementations.

A Laravel application model is not automatically a MyTree domain entity. In particular, a future `Source`, `Mention`, `Claim`, hypothesis or interpretation concept must not become an Eloquent model merely because it is persisted by Laravel.

### Application and contracts

Use cases and application-owned boundaries belong under:

```text
app/Application/<Capability>/...
```

Application code may depend on Domain and application contracts. It coordinates work but does not contain low-level persistence, HTTP, queue, filesystem or framework details.

Interfaces are introduced only for real boundaries, for example when:

- infrastructure must be replaceable,
- another capability/package must depend on a stable application-owned contract,
- multiple implementations are expected,
- a deterministic/testable use case needs an external capability supplied from outside.

Do not create one interface per class or placeholder contracts for hypothetical future implementations.

### Infrastructure and adapters

Framework- and implementation-specific code belongs under:

```text
app/Infrastructure/<Concern>/...
```

Eloquent persistence has the explicit home:

```text
app/Infrastructure/Persistence/Eloquent/...
```

Eloquent models are persistence records/adapters, not the canonical MyTree domain model. Laravel migrations and factories remain under the conventional `database/` directory because they are framework persistence tooling.

External packages and processes such as Index Providers, Scan Providers, name processing and MyTree Engine implementations must be integrated through explicit adapters/contracts. Concrete package-specific DTOs, clients and implementation details should remain at the Infrastructure boundary instead of leaking through Domain, Application or Filament code.

The Engine integration boundary must remain implementation-language agnostic as required by the canonical project architecture.

### Filament and other entry points

Filament UI code belongs under:

```text
app/Filament/...
```

Resources, Pages, Forms, Actions and Widgets are UI adapters. They may validate/present input and invoke application behavior, but they must not become the Source/Claim domain model or contain reusable domain rules.

Console commands under `app/Console` are also framework entry points. Laravel service providers under `app/Providers` are composition roots for bindings and adapter wiring.

Framework-managed UI/entry-point classes may use Laravel container facilities at the edge when the framework controls their construction. Service-location/container access is not permitted as a hidden dependency inside Domain or Application code. Constructor injection remains the default for replaceable collaborators whenever normal construction is available.

## Capability layout

Future capabilities use consistent capability names across only the layers they actually require. Examples include:

| Capability | Possible homes when real code exists |
| --- | --- |
| Acquisition | `Domain/Acquisition`, `Application/Acquisition`, `Infrastructure/Acquisition`, `Filament/Acquisition` |
| Settings | `Application/Settings`, `Infrastructure/Persistence/Eloquent/Settings`, `Filament/Settings` |
| Search | `Domain/Search`, `Application/Search`, `Infrastructure/Search`, `Filament/Search` |
| Providers | application contracts/use cases plus `Infrastructure/Providers` adapters |
| Engine | `Application/Engine` orchestration/contracts, `Infrastructure/Engine` adapters, `Filament/Engine` UI |
| Viewer | application/query code plus `Filament/Viewer`; add Domain/Infrastructure only if justified |
| Research | `Domain/Research`, `Application/Research`, `Infrastructure/Research`, `Filament/Research` as needed |

These are placement conventions, not a request to create these directories now.

When one capability needs another, prefer a stable Domain/Application contract rather than reaching into another capability's Infrastructure implementation. Cross-capability events are appropriate only when they provide meaningful decoupling; straightforward local calls remain preferable when no boundary is gained.

## Current baseline mapping

The current application intentionally has no MyTree genealogical Domain or Application hierarchy yet.

Existing framework-specific code is placed explicitly as infrastructure:

```text
app/Infrastructure/Diagnostics/SystemStatus.php
app/Infrastructure/Persistence/Eloquent/Models/User.php
```

`User` is Laravel/Filament authentication persistence. It is not a genealogical `Person` domain model.

The system status widget remains a Filament adapter:

```text
app/Filament/Widgets/SystemStatusWidget.php
```

This baseline demonstrates the placement rule without introducing Source/Mention/Claim or speculative future modules.

## Testing and enforcement

`tests/Architecture/LayerDependencyTest.php` provides lightweight guardrails:

- PHP files under `app/Domain` and `app/Application` may not depend on Laravel or Filament namespaces or Laravel container/configuration helpers,
- application Eloquent dependencies must live under `app/Infrastructure/Persistence/Eloquent`.

The test is intentionally small and dependency-free. It complements, rather than replaces, code review, PHPUnit behavior tests and Larastan/PHPStan.

When a new real boundary appears, update this test only if the repository convention itself changes; do not weaken it merely to accommodate outward dependencies in core code.

## Change checklist

When adding a capability or class, ask in this order:

1. Is this a framework-independent domain concept? Put it in `Domain`.
2. Is this a use case or stable application-owned contract/DTO? Put it in `Application`.
3. Is this Eloquent, Laravel, transport, storage or a concrete external integration? Put it in `Infrastructure`.
4. Is this Filament/Console/framework entry-point code? Keep it at the framework edge and invoke inward behavior.
5. Does the proposed abstraction solve a real replacement/integration/testing boundary? If not, keep the design concrete and simple.

Repository changes must continue to satisfy `./ops/test.sh` and the canonical MyTree coding standard.
