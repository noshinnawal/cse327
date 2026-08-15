# Automated Test Suite for block327 — Design

Date: 2026-08-15
Status: Approved (user approved design sections; spec written for the record)

## Goal

Add an automated test suite to the certificate verification system so the backend
logic (hash generation, authentication, ledger insert/verify/delete/search) is
verified by code rather than by hand. This demonstrates the software-engineering
practice of automated testing for CSE327.

## Decisions (confirmed with user)

1. **Automated test suite** — real test code, not a manual checklist.
2. **Zero-dependency runner** — no Composer/PHPUnit; a small custom runner that
   works on a bare XAMPP install (`C:\xampp\php\php.exe`).
3. **SQLite in-memory** — tests run against a fresh in-memory SQLite DB
   (`pdo_sqlite`, available in XAMPP PHP 8.2), never touching the real MySQL
   ledger. Zero setup, full isolation.
4. **Refactor for testability** — extract business logic into a `core.php`
   functions library; handlers become thin HTTP wrappers. App behavior unchanged.

## Architecture

### 1. `core.php` (new) — pure logic, no HTTP globals

All functions take a `$pdo` parameter and do no `echo`:

- `pdf_hash($path)` — SHA-256 of a file (wraps `hash_file`).
- `ledger_insert($pdo, $hash, $student_name, $degree, $institution, $issuance_date)`
  — inserts a certificate row; throws `PDOException` (code 23000) on duplicate hash.
- `ledger_find_by_hash($pdo, $hash)` — returns the matching row or `false`.
- `ledger_delete($pdo, $id, $institution)` — deletes only rows owned by the given
  institution; returns `true` if a row was actually deleted.
- `ledger_search($pdo, $institution, $q, $sort)` — search + sort query from
  view_certs.php, including the sort-whitelist fallback to `created_at DESC`.

### 2. Thin wrappers (modified, behavior unchanged)

- `issue_handler.php` — auth check, read `$_FILES`/`$_POST`, call `pdf_hash` +
  `ledger_insert`, same JSON responses, same temp-file cleanup.
- `verify_handler.php` — call `pdf_hash` + `ledger_find_by_hash`, same JSON.
- `delete_handler.php` — call `ledger_delete`, same JSON.
- `view_certs.php` — replace inline query with `ledger_search($pdo, ...)`.
- `auth.php` — untouched (functions already use `global $pdo`).

### 3. `db.php` (modified) — env-var overrides, same defaults

`DB_DSN`, `DB_USER`, `DB_PASS` env vars override the MySQL defaults so tests can
point the app code at SQLite via `putenv()` before including files. Production
behavior is unchanged when the env vars are unset.

### 4. Test runner `tests/run.php` (new, zero dependency)

- Assert helpers: `assert_true`, `assert_eq`, `assert_contains`, `assert_throws`.
- Discovers `*.test.php` files under `tests/` recursively; runs every global
  function named `test_*`; any exception fails the test.
- Prints per-test ✓/✗, a failure summary, and exits 0/1 (1 = any failure) so it
  can be wired into automation later.
- `run_tests.bat` (new) — double-clickable launcher that uses `php` from PATH or
  falls back to `C:\xampp\php\php.exe`.

### 5. Test fixtures & helpers

- `tests/helpers.php` — `boot_sqlite()`: fresh `PDO('sqlite::memory:')` with the
  same attributes as db.php (ERRMODE_EXCEPTION, FETCH_ASSOC), loads the test
  schema; `seed_certificate()` helper for convenient test data.
- `tests/fixtures/schema.sqlite.sql` — SQLite port of schema.sql: `TEXT` instead
  of `VARCHAR`/`ENUM` (+CHECK constraint), `INTEGER PRIMARY KEY AUTOINCREMENT`,
  `INSERT OR IGNORE` seeds for the two demo institutions.

### 6. Test suites

- `tests/unit/hash.test.php` — same content → same hash; different content →
  different hash; output is 64 lowercase hex chars.
- `tests/unit/auth.test.php` — active + correct password → institution name;
  wrong password → null; pending account → `'pending'`; unknown institution → null.
- `tests/integration/ledger.test.php` — the real flows:
  - issue a PDF → verify same PDF → valid, metadata round-trips
  - issue → tamper one byte → not found (invalid)
  - duplicate issuance → integrity-constraint error (23000)
  - search by name/degree, no-match, sort by name/date, unknown-sort fallback
  - delete own certificate → removed from ledger
  - delete another institution's → refused, still present
  - re-issue the same PDF after deletion → succeeds and verifies

Each test function gets a fresh in-memory DB (isolation; nothing to clean up).

## Error handling

- Tests fail loudly with descriptive assertion messages (expected vs. actual).
- The runner catches `Throwable` so one failing test never halts the suite.
- `boot_sqlite()` throws if `pdo_sqlite` is missing (it is present in XAMPP 8.2).

## Verification

1. `php -l` on every modified/created PHP file.
2. `C:\xampp\php\php.exe tests\run.php` — all tests pass.
3. Live smoke test of the app via XAMPP/MySQL (index.php renders, verify flow
   responds) to prove the refactor changed nothing.
4. README gains a "Testing" section documenting how to run the suite.

## Out of scope

- Frontend/UI testing (pages are static HTML + vanilla JS).
- CI integration (exit code 1 on failure makes it CI-ready later).
- Testing the handler-level branches that stay in the wrappers
  (unauthenticated requests, missing metadata) — covered manually.