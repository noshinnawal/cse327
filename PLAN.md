# Localized Academic Certificate Verification System

Based on the provided proposal, the goal is to build a locally hosted web-based document verification ecosystem. This system will allow universities to issue tamper-proof digital certificates and recruiters to instantly verify them using a zero-trust model without relying on complex cloud infrastructure. The stack is strictly vanilla HTML/CSS for the frontend, vanilla PHP for backend processing, and MySQL for the database.

## Implementation Details Confirmed

> [!NOTE]
> - **Database:** We will proceed exclusively with **MySQL**, using PDO for secure queries.
> - **Environment:** The project will be structured so it can simply be dropped into the `htdocs` directory of a standard XAMPP installation and work seamlessly.
> - **Issuance Workflow:** Based on the proposal, the system will accept an uploaded PDF, generate its SHA-256 hash, link it to the previous record to form a local blockchain, insert the hashes and metadata into the MySQL ledger, and then immediately discard the uploaded PDF from the server to save space and ensure privacy.

## Proposed Changes

### Database Layer

#### [NEW] [schema.sql](schema.sql)
Defines the structure for the certificate ledger. It will contain a `certificates` table storing the document hash, previous block hash, record hash, revocation status, student metadata (name, degree, issuance date), and a unique certificate ID.

#### [NEW] [db.php](db.php)
Establishes a secure PDO connection to the database.

---

### Backend Logic & Cryptography

#### [NEW] [issue_handler.php](issue_handler.php)
Processes form submissions from the issuance dashboard. It will accept a PDF file upload, generate its SHA-256 hash using `hash_file()`, securely insert the hash and metadata into the database using parameterized queries, and immediately delete the uploaded file.

#### [NEW] [verify_handler.php](verify_handler.php)
Processes file uploads from the public verification portal. It will generate the SHA-256 hash of the uploaded PDF and query the database to verify the blockchain integrity (record hash and previous hash link), returning the associated metadata if authentic, or a "Tamper Alert" if the chain is broken or revoked.

---

### Frontend UI (Vanilla HTML5/CSS3)

#### [NEW] [style.css](style.css)
A highly polished, modern, and rich CSS stylesheet to ensure the UI looks premium. It will feature a sleek design, smooth gradients, modern typography (Inter/Roboto), and micro-animations for interactions like file drag-and-drop.

#### [NEW] [index.php](index.php)
The public verification landing page. It will feature a drag-and-drop file interface for recruiters to upload a certificate for immediate validation. It will use AJAX to communicate with `verify_handler.php` without reloading the page.

#### [NEW] [dashboard.php](dashboard.php)
The Institutional Issuance Dashboard. A secure portal for registrars to input student metadata, upload a degree PDF, and register it in the ledger.

## Verification Plan

### Automated/Manual Testing
- Copy files to an XAMPP `htdocs` directory and start Apache & MySQL.
- Execute `schema.sql` in phpMyAdmin.
- Create a sample dummy PDF document.
- Use the `dashboard.php` portal to register the dummy PDF and verify it is successfully inserted into the database and the original file is discarded.
- Upload the exact same PDF via the `index.php` verification portal and ensure it shows as valid.
- Modify a single byte of the PDF (or use a different PDF) and upload it to the verification portal to ensure the Tamper Alert System successfully flags it as invalid.
- Verify the UI aesthetics meet the premium, modern standards requested.
