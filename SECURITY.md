# Security Policy

Security is a first-class concern of this project (Lec 3: secure coding practices,
OWASP Top 10, threat modeling).

## Supported Versions

| Version | Supported |
|---------|-----------|
| main (development) | Yes — fixes land via reviewed PRs |

## Reporting a Vulnerability

This is a course project, but vulnerabilities should still be reported responsibly:

1. **Do not** open a public issue describing an exploit.
2. Email your course instructor (Prof. Atiqur Rahman) and the project lead, or open a
   **private** GitHub advisory (Security tab → New advisory).
3. Include: affected version/commit, steps to reproduce, expected vs actual behavior.

We will acknowledge within 7 days and aim to fix within 14.

## Security Posture (implemented)

| Control | Where |
|---------|-------|
| CSRF tokens on all state-changing requests | `csrf.php`, all handlers and forms |
| Stored-XSS defense (server-side HTML escaping) | `core.php::certificate_present`, `verify_handler.php` |
| Upload validation (PDF magic bytes, 5 MB cap) | `core.php::validate_upload` |
| Brute-force lockout (5 failures → 15 min) | `auth.php::authenticate` |
| bcrypt password hashing (never plaintext) | `register.php`, `schema.sql` seeds |
| Session hardening (HttpOnly, SameSite=Lax, regenerate-on-login, 30-min idle timeout) | `auth.php`, `login.php`, `logout.php` |
| Parameterized SQL everywhere (no string-built queries) | `core.php`, `auth.php` |
| No client-visible database error leaks | all `*_handler.php`, `register.php` |
| Audit log of issue/verify/delete/login events | `core.php::audit_log` |
| Uploads deleted immediately after hashing (privacy by design) | `issue_handler.php`, `verify_handler.php` |

## Production Hardening Checklist

Before deploying beyond a course demo:

- [ ] Serve over HTTPS (the session cookie's `Secure` flag activates automatically)
- [ ] Set strong `DB_USER`/`DB_PASS` credentials instead of XAMPP defaults
- [ ] Restrict the `institutions` activation path (currently manual via database)
- [ ] Rotate the seeded demo credentials in `schema.sql`
- [ ] Review the audit log periodically
