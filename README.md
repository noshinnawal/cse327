# block327 — Localized Academic Certificate Verification System

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
  login.php ──► dashboard.php (issue a certificate)
                └──► view_certs.php (search, sort, and correct mistakes:
                     deleting a row lets the same PDF be re-issued)
```

The uploaded PDF is hashed and immediately deleted — the server never stores the document itself. A certificate's integrity is proven by hash comparison alone.

## Project Structure

```
block327/
├── index.php            — Public verification portal (no login required)
├── login.php            — Institution login (multi-institution)
├── auth.php             — Session guard + hardcoded institution credentials
├── logout.php           — Destroys the session
├── dashboard.php        — Protected issuance dashboard
├── view_certs.php       — Protected ledger: search, sort, delete
├── issue_handler.php    — Backend: hash generation + ledger insertion
├── verify_handler.php   — Backend: hash comparison + verification result
├── delete_handler.php   — Backend: removes a certificate from the ledger
├── db.php               — PDO MySQL connection (configurable)
├── schema.sql           — MySQL schema for the `certificates` table
├── style.css            — Light-theme UI (Inter font, indigo accents)
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

Credentials are hardcoded in `auth.php` (one password per institution):

| Institution | Password |
|-------------|----------|
| North South University | `nosh327` |
| Brac University | `brac327` |

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
- Login uses **hardcoded demo credentials** and session flags only — replace `$institutions` in `auth.php` with a database-backed `users` table (password hashing with `password_hash()` / `password_verify()`) before production use
- Deleting a certificate is permanent: a deleted certificate shows as "not valid" for future checks, so deletion is intended for correcting issuance mistakes before the certificate reaches the public
- The system does not authenticate users beyond the session check — add HTTPS and stronger authentication for production use

## License

MIT — see [LICENSE](LICENSE).
