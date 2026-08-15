# block327 — Localized Academic Certificate Verification System

![CI](https://github.com/noshinnawal/cse327/actions/workflows/ci.yml/badge.svg)

A zero-trust, tamper-proof digital certificate issuance and verification platform. Universities issue certificates by recording their SHA-256 hash in a local MySQL ledger; recruiters verify certificates by re-hashing the PDF and checking the ledger. No cloud, no blockchain, no third-party dependency — just a MySQL database and a vanilla PHP backend.

## How It Works

```
Verification (Recruiter / Employer — no login required)
  ┌─────────────────────┐     ┌──────────────────┐     ┌──────────────────┐
  │ Upload PDF          │────>│ SHA-256 hash     │────>│ Query ledger     │
  │                     │     │ (PDF discarded)  │     │ → match = valid  │
  └─────────────────────┘     └──────────────────┘     │ → no match =     │
                                                        │   not valid      │
                                                        └──────────────────┘

Issuance (University Registrar — login required)
  register.php ─► (pending) ─► admin approval ─► login.php ─► dashboard.php
                    └──► view_certs.php (search, sort, and correct mistakes:
                         deleting a row lets the same PDF be re-issued)
```

The uploaded PDF is hashed and immediately deleted — the server never stores the document itself. A certificate's integrity is proven by hash comparison alone.

## Project Structure

```
block327/
├── index.php            — Public verification portal (no login required)
├── login.php            — Institution login (database-backed, active accounts only)
├── register.php         — Institution self-registration (pending until admin approval)
├── auth.php             — Session guard + database-backed authentication
├── logout.php           — Destroys the session
├── dashboard.php        — Protected issuance dashboard
├── view_certs.php       — Protected ledger: search, sort, delete
├── issue_handler.php    — Backend: hash generation + ledger insertion
├── verify_handler.php   — Backend: hash comparison + verification result
├── delete_handler.php   — Backend: removes a certificate from the ledger
├── core.php             — Shared ledger logic (insert/find/delete/search/hash)
├── db.php               — PDO MySQL connection (configurable)
├── schema.sql           — MySQL schema: `certificates` + `institutions` tables
├── style.css            — Light/dark theme UI (Inter font, indigo accents)
├── tests/               — Automated test suite (zero-dependency, SQLite in-memory)
├── run_tests.bat        — Double-click test runner
├── .github/             — CI workflow (lint + tests) and pull request template
├── PLAN.md              — Implementation plan and design decisions
├── proposal.pdf         — Original project proposal
└── .gitignore
```

## Requirements

- **Web server**: Apache (via XAMPP or WampServer) with PHP 8.0+
- **Database**: MySQL 5.7+ or MariaDB 10.3+ (included with XAMPP/WampServer)
- **PHP extensions**: `pdo_mysql`, `fileinfo` (enabled by default in XAMPP)

## Setup (Windows)

### 1. Install XAMPP

Download and install [XAMPP](https://www.apachefriends.org/) for Windows. This gives you Apache, MySQL, and PHP all in one package.

### 2. Start Apache & MySQL

Open the **XAMPP Control Panel** and click **Start** for both **Apache** and **MySQL**.

### 3. Create the database

Open the **XAMPP Control Panel** and click **Admin** on the MySQL row (or open your browser and go to `http://localhost/phpmyadmin`). Then:

- Click the **SQL** tab
- Paste the entire contents of `schema.sql` into the editor
- Click **Go**

Alternatively, from a command prompt (run as Administrator):

```batch
cd C:\xampp\mysql\bin
mysql -u root < C:\path\to\block327\schema.sql
```

> If you created the database with an **older version** of the schema, add the missing column once:
> ```sql
> ALTER TABLE certificates ADD COLUMN institution VARCHAR(255) NOT NULL DEFAULT '' AFTER issuance_date;
> ```

### 4. Deploy the files

Copy all project files into the XAMPP web root:

```batch
xcopy C:\path\to\block327\* C:\xampp\htdocs\block327\ /E
```

### 5. Configure database credentials

Edit `db.php` if needed — the defaults (`root` / no password / `localhost`) match a fresh XAMPP install:

```php
$host = '127.0.0.1';
$db   = 'nosh_softdev';
$user = 'root';
$pass = '';
```

### 6. Open the app in your browser

| Page | URL |
|------|-----|
| Verification portal | `http://localhost/block327/index.php` |
| Institution login | `http://localhost/block327/login.php` |

## Development (VS Code)

### 1. Install VS Code

Download and install [Visual Studio Code](https://code.visualstudio.com/) for Windows.

### 2. Open the project

```batch
code C:\xampp\htdocs\block327
```

Or open VS Code and use **File → Open Folder**.

### 3. Recommended Extensions

| Extension | ID | Purpose |
|-----------|----|---------|
| **PHP Intelephense** | `bmewburn.vscode-intelephense-client` | PHP code intelligence, autocomplete, navigation, hover info |
| **MySQL** | `cweijan.vscode-mysql-client2` | Browse the database, run queries, inspect tables directly inside VS Code |
| **Live Server** | `ritwickdey.LiveServer` | Auto-reload the frontend when you edit HTML/CSS (point it at the XAMPP URL) |
| **PHP Debug** | `xdebug.php-debug` | Step-through PHP debugging with Xdebug (see setup below) |
| **Prettier** | `esbenp.prettier-vscode` | Format CSS, JSON, and markdown on save |
| **EditorConfig** | `EditorConfig.EditorConfig` | Respect `.editorconfig` conventions across editors |

### 4. PHP Debugging Setup (Xdebug)

XAMPP for Windows ships with Xdebug pre-installed. To enable step-through debugging:

1. Open `C:\xampp\php\php.ini` and verify or add:
   ```ini
   xdebug.mode = debug
   xdebug.start_with_request = yes
   xdebug.client_port = 9003
   ```

2. In VS Code, create `.vscode/launch.json` in the project root:
   ```json
   {
       "version": "0.2.0",
       "configurations": [
           {
               "name": "Listen for Xdebug",
               "type": "php",
               "request": "launch",
               "port": 9003,
               "pathMappings": {
                   "C:\\xampp\\htdocs\\block327": "${workspaceFolder}"
               }
           }
       ]
   }
   ```

3. Restart Apache from the XAMPP Control Panel.

4. Set a breakpoint in VS Code (click the gutter next to a line number) and press **F5** — it will attach to any PHP request hitting `http://localhost/block327/`.

### 5. Workflow tips

- **PHP files**: Edit, save, refresh `http://localhost/block327/` in your browser — no build step
- **CSS changes**: Edit `style.css` and refresh — Live Server extension can automate this
- **Database schema changes**: Use the MySQL extension to run SQL directly from VS Code, or open `http://localhost/phpmyadmin` in your browser
- **Commit from VS Code**: Use the **Source Control** tab (Ctrl+Shift+G) to stage, commit, and push

## Usage

### Demo login credentials

Credentials are stored in the `institutions` table (seeded by `schema.sql`), with bcrypt-hashed passwords:

| Institution | Password |
|-------------|----------|
| North South University | `nosh327` |
| Brac University | `brac327` |

### Register a New Institution

1. Open `login.php` and click **Register your institution**
2. Enter the institution name, location, email, website, representative name, job title, and a password
3. The account is created with `pending` status and **cannot log in yet**
4. To activate it, open the `institutions` table in phpMyAdmin and set `status = 'active'` for the signup you trust (verify their website/email first)
5. The institution can now log in normally

### Issue a Certificate

1. Open `index.php` and click **Institution Login** (or go straight to `login.php`)
2. Sign in with an institution + password
3. Enter student name, degree, and issuance date
4. Upload the PDF certificate
5. On success, the hash + metadata + your institution are recorded in the ledger and the uploaded PDF is deleted

### View, Search, and Correct the Ledger

1. From the dashboard, click **View Certificates**
2. Only the logged-in institution's certificates are listed
3. Search by student name or degree, and sort by date/name
4. **Delete** removes the row permanently (with a confirmation dialog) — a deleted certificate no longer verifies, and the same PDF can be re-registered afterwards

### Verify a Certificate

1. Open `index.php` (no login required)
2. Drag and drop a PDF certificate onto the upload zone
3. The system returns the certificate metadata (including the issuing institution) if authentic, or a **"not valid"** alert if the document was tampered with, altered, or removed from the ledger

## Philosophy

- **Zero trust**: Not even the issuing server can alter a certificate after registration without breaking the hash
- **Privacy by design**: No certificate content is ever stored — only its hash
- **No dependencies**: Pure PHP + MySQL, no frameworks, no external APIs, no cloud services
- **Drop-in deployment**: Works out of the box in any standard LAMP/XAMPP environment

## Security Notes

- The uploaded PDF is **immediately deleted** after hashing — the server never retains the document
- SHA-256 collision resistance makes it computationally infeasible to produce a different document with the same hash
- Institution credentials are stored in the `institutions` table with `password_hash()` / `password_verify()` — plaintext is never stored
- New institutions register with `pending` status and are activated manually via the database, preventing random impersonation of real universities
- Deleting a certificate is permanent: a deleted certificate shows as "not valid" for future checks, so deletion is intended for correcting issuance mistakes before the certificate reaches the public
- The system does not authenticate users beyond the session check — add HTTPS and stronger authentication for production use

## Testing

The project ships with a zero-dependency automated test suite (no Composer, no PHPUnit):

| Command | What it does |
|---------|--------------|
| `C:\xampp\php\php.exe tests\run.php` | Run all unit + integration tests |
| `run_tests.bat` | Double-clickable launcher for the same |

The suite covers:

- **Hash behavior** — same file always produces the same hash; a tampered file produces a different hash; output format is 64 hex characters
- **Authentication** — active institution + correct password logs in; wrong password is rejected; pending accounts cannot log in; unknown institutions return null
- **Ledger flows** — issue a PDF then verify it as valid; a tampered document is flagged invalid; duplicate issuance is rejected; search and sort (name/degree filters, name/date ordering, unknown-sort fallback); delete only works for the owning institution; the same PDF can be re-issued after deletion

Tests run against a fresh **in-memory SQLite database** (PHP's built-in `pdo_sqlite`), so they never touch your real MySQL ledger and need zero setup — every test starts with a clean database. Exit code is 0 on success and 1 on any failure, so the suite can be wired into CI later.

## GitHub Workflow (Team)

Every change to `main` goes through a reviewed, CI-verified pull request:

1. Create a branch: `git checkout -b feature/short-description`
2. Commit your work, then push: `git push -u origin feature/short-description`
3. Open a pull request on GitHub (the template is pre-filled) and assign a teammate as reviewer
4. **CI runs automatically** on the PR — lint + test suite must pass
5. A teammate reviews and approves, then merges; the branch is deleted automatically
6. Back on `main`: `git checkout main && git pull`

`main` is branch-protected: direct pushes are blocked, status checks must pass, and one teammate approval is required. CI runs on every push and every PR — watch the badge above or the **Actions** tab.

## License

MIT — see [LICENSE](LICENSE).
