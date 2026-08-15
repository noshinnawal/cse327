# Applied Design Patterns & GRASP

This document maps the patterns and principles taught in Lec 9–13 to concrete code in
this project. Each entry cites the lecture definition so reviewers can verify the mapping.

## Creational — Singleton (Lec 10)

> Intent: Ensure a class has only one instance, and provide a global point of access to it.

**Implementation:** `db.php` — `DbConnection::getInstance()` lazily creates the single PDO
connection and returns it to every caller. Handlers and `auth.php` access the same
instance; the backwards-compatible `$pdo` global preserves the original interface.

**Consequence applied:** controlled access to the sole instance, no duplicate connections.

## Creational — Factory Method (Lec 10)

> Intent: Define a creation interface, but let subclasses choose the object type to instantiate.

**Implementation:** `core.php` — `HashStrategyFactory::create('sha256')` returns a
`Sha256HashStrategy` without callers knowing the concrete class. Adding SHA-512 later
means adding one class and one branch — no caller changes.

## Behavioral — Strategy (Lec 12)

> Intent: Define a family of algorithms, encapsulate each one, and make them interchangeable.

**Implementation:** `core.php` — `HashStrategy` interface with `Sha256HashStrategy`.
`pdf_hash()` delegates to the strategy, so the hashing algorithm is a swappable family
rather than a hard-coded function call. This directly supports the project's core
promise: tamper-proof, upgradeable document fingerprinting.

## Structural — Facade (Lec 11)

> Intent: Provide a simplified interface to a complex subsystem.

**Implementation:** `core.php` — `ledger_insert()`, `ledger_find_by_hash()`,
`ledger_delete()`, `ledger_search()` are the single facade the UI handlers use for ALL
database work. Handlers never write SQL; swapping the storage subsystem only touches
`core.php` (this is also why the test suite can run against SQLite with zero changes —
the facade is the Adapter point, see below).

## Structural — Adapter (Lec 11)

> Intent: Convert a class interface to one clients expect; enable incompatible classes to work together.

**Implementation:** the PDO abstraction adapts two storage engines: MySQL in production
and SQLite in-memory in tests. `db.php` reads the `DB_DSN` environment variable, so the
test harness (`tests/helpers.php`) swaps the database without modifying any client code.

## GRASP — Controller (Lec 13)

> Solution: Assign system event handling to a class that represents a specific use case.

**Implementation:** each handler is a dedicated use-case controller:

| Controller | Use case | File |
|------------|----------|------|
| `IssueController` (implied) | Issue a certificate | `issue_handler.php` |
| `VerifyController` (implied) | Verify a certificate | `verify_handler.php` |
| `DeleteController` (implied) | Delete a certificate | `delete_handler.php` |
| Login/Register (form controllers) | Authenticate / register | `login.php`, `register.php` |

Each controller receives the event (HTTP POST), validates it (CSRF + input), delegates to
the core layer, and formats the response — keeping UI concerns out of the business logic.

## GRASP — Information Expert (Lec 13)

> Solution: Assign a responsibility to the class that has the information necessary to fulfill it.

**Implementation:** `certificate_present()` (data shaping) and `ledger_search()` (query
composition with whitelisted sort columns) live in `core.php`, which owns the ledger
schema knowledge. Handlers hold no schema details.

## GRASP — Low Coupling / High Cohesion (Lec 13)

- **Low coupling:** pages/handlers depend only on `auth.php`, `core.php`, `csrf.php`,
  `db.php` — no cross-page dependencies, no shared mutable state beyond the session.
- **High cohesion:** hashing, validation, ledger access, and audit logging are separate
  functions in `core.php`; each has a single responsibility and its own tests.

## Template Method (Lec 12) — note

The five pages (`index.php`, `login.php`, `register.php`, `dashboard.php`, `view_certs.php`)
share the same layout skeleton (topbar + theme toggle + CSRF meta). Extracting
`partials/header.php` / `partials/footer.php` would formalize this as a Template Method;
currently deferred to avoid churn before the report deadline (see risk R-04).

## Honest note on omitted patterns

Facade/Controller/Singleton/Strategy are applied where they solve a real problem. Forcing
e.g. Observer (no event subscribers exist) or Builder (no complex object assembly exists)
would be pattern-for-pattern's-sake and is intentionally avoided.
