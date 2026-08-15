# Risk Register

Risk management process (Lec 5): identification → analysis (probability × impact) →
planning (response) → monitoring. This register is tracked alongside GitHub Issues
(tagged `risk`) and reviewed weekly.

Risk classification (Lec 5): **Project** risks affect schedule/resources; **Product**
risks affect quality/performance; **Business** risks affect the organization.

Probability: VL / L / M / H / VH. Impact: Insignificant / Tolerable / Serious / Catastrophic.

| ID | Category | Risk | Probability | Impact | Response | Owner | Status |
|----|----------|------|-------------|--------|----------|-------|--------|
| R-01 | Project | A team member becomes unavailable (illness, load) | M | Serious | **Mitigate**: pair-review all code in PRs so knowledge is shared; keep weekly progress reports as the single source of truth | All | Open |
| R-02 | Product | MySQL vs SQLite behavioral drift (e.g., `UNIQUE` handling, date types) breaks production behavior that tests don't catch | M | Serious | **Mitigate**: dedicated CI job runs the integration suite against a real MySQL service container | All | Open |
| R-03 | Product | Hash collision / tamper false-positive complaints from non-experts | L | Tolerable | **Avoid**: explain SHA-256 collision resistance in README and verification UX copy | — | Accepted |
| R-04 | Project | Scope creep — requests to add features (user management, PDF generation) beyond the proposal | H | Serious | **Avoid**: feature freeze after this milestone; any new feature must be renegotiated with the group (Lec 4: scope/time/cost triangle) | Lead | Open |
| R-05 | Product | Schema migration drift between `schema.sql` and the SQLite test fixture | M | Serious | **Mitigate**: update both files in the same commit; CI MySQL job exercises `schema.sql` directly | All | Open |
| R-06 | Business | A real university impersonates another institution during self-registration | M | Serious | **Avoid**: registration requires manual admin activation; demo registrars verify website/email before activating (README) | Admin | Open |
| R-07 | Product | Brute-force credential attacks on the login form | M | Serious | **Mitigate**: implemented — 5-failure/15-minute lockout (FR-07) + audit logging of failed logins | All | **Closed** |
| R-08 | Product | Cross-site request forgery or stored XSS via certificate metadata | M | Serious | **Mitigate**: implemented — CSRF tokens on all state-changing requests (FR-09) and server-side HTML escaping (FR-10) | All | **Closed** |
| R-09 | Product | Disk exhaustion / malware risk from arbitrary file uploads | M | Serious | **Mitigate**: implemented — PDF magic-byte validation + 5 MB cap (FR-08); uploads deleted immediately after hashing | All | **Closed** |
| R-10 | Project | Report/presentation deadlines collide with exam preparation (final report due Aug 16–18) | VH | Serious | **Contingency**: documentation-first work order; docs and tests are completed before optional polish | Lead | Open |

## Monitoring cadence

- Review this register at every weekly progress meeting (Week 1–4 reports exist; Week 5+ to follow).
- Track open risks as GitHub Issues tagged `risk`; close an issue only when the risk is eliminated or accepted.
- Re-evaluate probability/impact whenever a milestone passes or a risk indicator appears (Lec 5 risk indicators: schedule slips, requirement change requests, tool complaints).
