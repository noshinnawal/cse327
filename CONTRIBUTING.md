# Contributing

## Getting Started

1. Clone the repo and copy files into `C:\xampp\htdocs\block327\` (see README → Setup).
2. Install the dev tooling (optional but recommended):

   ```batch
   composer install
   composer test        :: run the test suite
   composer analyse     :: PHPStan static analysis
   composer fix         :: auto-format with PHP CS Fixer
   ```

## Development Workflow

`main` is protected — every change goes through a reviewed, CI-verified pull request.

1. `git checkout -b feature/short-description`
2. Make atomic commits with Conventional Commit messages:
   `feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `ci:`, `chore:`
3. Open a PR using the template and assign a teammate as reviewer.
4. CI must pass: lint, tests, static analysis, style check.
5. Get approval, merge, delete the branch.

## Definition of Done

A change is done only when **all** of these hold:

- [ ] Implements or fixes exactly one requirement (see `docs/requirements.md`)
- [ ] Automated tests added/updated — one test per affected requirement, named
      `test_FRxx_...` / `test_NFRxx_...`
- [ ] `composer test` passes locally (or `C:\xampp\php\php.exe tests\run.php`)
- [ ] `php -l` clean on changed files
- [ ] Manual smoke test performed if a UI or handler flow changed
- [ ] Schema changes applied to BOTH `schema.sql` and `tests/fixtures/schema.sqlite.sql`,
      plus the README migration note
- [ ] Docs updated if behavior changed (`docs/requirements.md`, README)

## Code Review Checklist (Inspections, Lec 14)

For reviewers:

- Security: CSRF on every state-changing request; no unescaped output; no SQL
  concatenation; no client-visible error internals
- Tests: requirement-ID naming; a test would actually fail without the change
- Simplicity: does the change duplicate existing `core.php` capabilities instead of reusing them?
- Style: PSR-12 (enforced by CI), no dead code

## Ethics (Lec 3)

This project handles university credential metadata. Contributors must:

- Never commit real student documents or personal data to the repository
- Never weaken privacy guarantees (hash-only storage, immediate upload deletion)
- Report vulnerabilities per `SECURITY.md` rather than publicizing exploits
