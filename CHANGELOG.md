# Changelog

All notable changes are documented here. Format based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioned with tags `v1.0.0`+.

## [Unreleased]

### Added
- CSRF protection on all state-changing requests (`csrf.php`, FR-09)
- Server-side upload validation: PDF magic bytes + 5 MB cap (FR-08)
- Stored-XSS defense via `certificate_present()` escaping (FR-10)
- Brute-force lockout: 5 failed logins → 15-minute lock (FR-07)
- Audit log for issue/verify/delete/login events (`audit_log` table, FR-06)
- Session hardening: HttpOnly/SameSite cookies, regenerate-on-login, 30-min idle timeout (NFR-02)
- Input validation: issuance date format, field length caps, error-message hygiene (NFR-05)
- Singleton `DbConnection` + `HashStrategy`/Factory refactor (design patterns, Lec 10/12)
- Requirement-ID test naming + requirements/traceability docs
- Risk register, security policy, contributing guide
- Dev tooling: PHPStan, PHP CS Fixer, Composer scripts
- CI: PHP version matrix + real-MySQL integration job

### Changed
- Password policy: minimum 8 characters (was 6)
- Registration/login forms carry CSRF tokens
- Internal database errors are logged, not shown to clients

### Security
- Fix: stored XSS via certificate metadata in the verification result card
- Fix: missing CSRF tokens on issue/verify/delete/login/register
- Fix: brute-force-able login
- Fix: arbitrary non-PDF uploads accepted (file type trusted from the client)
