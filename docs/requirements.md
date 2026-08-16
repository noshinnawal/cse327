# Requirements Specification

Course: CSE327 — Software Engineering
Project: Checkr — Localized Academic Certificate Verification System

Requirements engineering (Lec 6): functional requirements state the services the system
provides; non-functional requirements constrain those services. Every requirement below is
traceable to an automated test (see the traceability section at the end).

## Actors

| Actor | Role |
|-------|------|
| Recruiter / Employer | Verifies a certificate (no login required) |
| Registrar | Logs in, issues certificates, manages the ledger |
| Admin | Activates pending institution registrations (via database) |

## Functional Requirements

| ID | Requirement |
|----|-------------|
| FR-01 | The system must let an institution register with name, location, email, website, representative details and a password of at least 8 characters. New accounts start with `pending` status and cannot log in until activated. |
| FR-02 | The system must authenticate active institutions by name + bcrypt password. Pending accounts, unknown institutions, and wrong passwords must be rejected. |
| FR-03 | A logged-in registrar must be able to issue a certificate: upload a PDF, have it SHA-256 hashed, and recorded in the blockchain ledger linked to the previous record with student name, degree, and issuance date. Issuing the same PDF twice must be rejected (unique document hash). |
| FR-04 | Any visitor must be able to verify a PDF: the system hashes the upload, verifies its blockchain link, and returns the certificate metadata if valid, or a tamper alert if the link is broken or revoked. |
| FR-05 | A registrar must be able to view, search (by student name or degree) and sort (by date or name) only their own institution's certificates, and revoke a certificate to correct issuance mistakes. Revoked certificates must no longer verify, but remain in the ledger to preserve blockchain integrity. |
| FR-06 | The system must record an audit log entry for issue, verify, revoke, and login events. Audit logging must never break the main flows. |
| FR-07 | After 5 consecutive failed logins, the account must be locked for 15 minutes. |
| FR-08 | Uploads must be validated server-side: only PDF files (verified by `%PDF` magic bytes) up to 5 MB are accepted. |
| FR-09 | Every state-changing request (login, register, issue, verify, revoke) must carry a session-bound CSRF token validated with `hash_equals`. |
| FR-10 | Certificate data returned for public display must be HTML-escaped so stored values cannot inject markup (XSS defense). |

## Non-Functional Requirements

| ID | Requirement | Category |
|----|-------------|----------|
| NFR-01 | SHA-256 hashes must be exactly 64 lowercase hex characters; identical documents produce identical hashes; any change to a document produces a different hash. | Product (integrity) |
| NFR-02 | Sessions must use HttpOnly + SameSite=Lax cookies, regenerate the session ID on login, and expire after 30 minutes of inactivity. | Product (security) |
| NFR-03 | The system must run with zero third-party runtime dependencies (vanilla PHP + MySQL) and deploy by copying files into an XAMPP `htdocs` directory. | Organizational (deployment) |
| NFR-04 | The uploaded PDF must be deleted from the server immediately after hashing; certificate content is never stored, only its hash (privacy by design). | External (ethics/privacy) |
| NFR-05 | Server error responses must not leak database or driver internals; errors are logged server-side instead. | Product (security) |
| NFR-06 | The test suite must run with zero setup against an in-memory SQLite database and never touch the real MySQL ledger. | Organizational (process) |

## Use Cases (tabular format per Lec 6)

### UC-1: Verify a Certificate

| Field | Value |
|-------|-------|
| Use-case Number | B.327.1 |
| Event (Stimulus) | A recruiter uploads a certificate PDF to the public portal |
| Actors | Recruiter (primary) |
| Overview | The PDF is hashed; a ledger lookup determines authenticity and returns metadata |
| Related Use-cases | UC-2 (issue) — verification depends on prior issuance |
| Typical Process | 1. Recruiter drops PDF onto `index.php` 2. System hashes the file (SHA-256) 3. System queries the ledger 4. On match: metadata returned; on no match: tamper alert 5. Uploaded file is deleted |
| Exceptions | Non-PDF upload → rejected; oversized upload → rejected; CSRF token missing → 403; database error → generic message logged server-side |

### UC-2: Issue a Certificate

| Field | Value |
|-------|-------|
| Use-case Number | B.327.2 |
| Event (Stimulus) | Registrar submits metadata + certificate PDF from the dashboard |
| Actors | Registrar (primary) |
| Overview | The PDF is hashed and recorded in the ledger with metadata; the file is discarded |
| Related Use-cases | UC-3 (ledger management), UC-4 (login) |
| Typical Process | 1. Registrar logs in (UC-4) 2. Enters student name, degree, issuance date 3. Uploads the PDF 4. System validates the file (PDF, ≤ 5 MB) 5. System hashes and inserts into the ledger 6. System deletes the upload 7. System writes an audit entry |
| Exceptions | Duplicate hash → "certificate already exists"; invalid date → rejected; missing fields → rejected |

### UC-3: Manage the Ledger

| Field | Value |
|-------|-------|
| Use-case Number | B.327.3 |
| Event (Stimulus) | Registrar opens the certificate list or revokes an entry |
| Actors | Registrar (primary) |
| Overview | Registrar searches, sorts, and revokes own certificates |
| Related Use-cases | UC-2 |
| Typical Process | 1. Registrar opens `view_certs.php` 2. Searches by name/degree and sorts 3. Optionally revokes a certificate (with confirmation) 4. System marks the row as revoked and writes an audit entry |
| Exceptions | Revoking another institution's certificate → rejected; CSRF token missing → 403 |

### UC-4: Institution Login

| Field | Value |
|-------|-------|
| Use-case Number | B.327.4 |
| Event (Stimulus) | Registrar submits credentials |
| Actors | Registrar (primary) |
| Overview | Active institutions authenticate; session is hardened |
| Related Use-cases | UC-2, UC-3, UC-5 (registration) |
| Typical Process | 1. Registrar selects institution and enters password 2. System verifies bcrypt hash 3. System regenerates the session ID and starts the session |
| Exceptions | Pending account → approval notice; 5 failures → 15-minute lock; CSRF missing → rejected |

### UC-5: Register an Institution

| Field | Value |
|-------|-------|
| Use-case Number | B.327.5 |
| Event (Stimulus) | A university submits registration details |
| Actors | Registrar (primary), Admin (secondary) |
| Overview | New institutions register with `pending` status until activated |
| Related Use-cases | UC-4 |
| Typical Process | 1. University fills the registration form 2. System validates fields (email, URL, password ≥ 8) 3. System inserts the account with `pending` status 4. Admin activates the account in the database |
| Exceptions | Duplicate institution name → rejected; invalid email/URL → rejected |

## Requirement-to-Test Traceability

| Requirement | Test(s) | Location |
|-------------|---------|----------|
| FR-01 | `test_FR01_pending_account_cannot_login` | `tests/unit/auth.test.php` |
| FR-02 | `test_FR02_*` (4 tests) | `tests/unit/auth.test.php` |
| FR-03 | `test_FR03_FR04_issue_then_verify_same_pdf`, `test_FR03_duplicate_issuance_rejected` | `tests/integration/ledger.test.php` |
| FR-04 | `test_FR04_tampered_document_does_not_verify` | `tests/integration/ledger.test.php` |
| FR-05 | `test_FR05_*` (5 tests) | `tests/integration/ledger.test.php` |
| FR-06 | `test_FR06_*` (3 tests) | `tests/unit/security.test.php` |
| FR-07 | `test_FR07_*` (3 tests) | `tests/unit/auth.test.php` |
| FR-08 | `test_FR08_*` (7 tests) | `tests/unit/upload.test.php` |
| FR-09 | `test_FR09_*` (5 tests) | `tests/unit/csrf.test.php` |
| FR-10 | `test_FR10_certificate_present_escapes_xss_fields` | `tests/unit/security.test.php` |
| NFR-01 | `test_NFR01_*` (3 tests) | `tests/unit/hash.test.php` |
| NFR-02..06 | Structural/process guarantees — verified via CI (multi-PHP matrix), schema fixtures, and code inspection (PR review) | — |
