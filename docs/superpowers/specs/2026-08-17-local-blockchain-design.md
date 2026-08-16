# Local Blockchain Implementation Design

## Purpose
Convert the existing centralized hash ledger into a local, mathematically linked blockchain to provide cryptographic proof of chronological issuance.

## Architecture: The "Every Certificate is a Block" Model
Instead of relying on complex background workers to batch records into blocks, every certificate issued will act as its own block in the chain. This maintains the project's philosophy of zero dependencies and drop-in deployment.

### 1. Database Schema Changes
The `certificates` table will be modified to support chained hashes and soft deletions (revocations):
- Rename `hash` column to `document_hash` (stores the SHA-256 of the PDF).
- Add `previous_hash` (VARCHAR 64, allows NULL for the genesis block).
- Add `record_hash` (VARCHAR 64) - This represents the hash of the "block".
- Add `is_revoked` (BOOLEAN, default 0) - Replaces hard deletes to maintain chain integrity.

### 2. Issuance Flow (`issue_handler.php`)
When a university issues a certificate, the system will:
1. Hash the uploaded PDF to generate the `document_hash`.
2. Query the database for the `record_hash` of the most recently inserted row. This becomes the `previous_hash`. (If the database is empty, `previous_hash` is NULL).
3. Calculate the new `record_hash` by concatenating: `document_hash + previous_hash + student_name + degree + issuance_date` and hashing the resulting string with SHA-256.
4. Insert the row into the `certificates` table.

### 3. Verification Flow (`verify_handler.php`)
When a recruiter uploads a PDF for verification, the system will perform targeted "Light Verification":
1. Hash the uploaded PDF to generate the `document_hash`.
2. Query the database for a row matching this `document_hash`.
3. If the row is found and `is_revoked == 1`, return a "Revoked" message.
4. **Integrity Check 1 (Self-Check):** Recalculate the `record_hash` using the row's data (`document_hash + previous_hash + student_name + degree + issuance_date`). It must perfectly match the stored `record_hash`.
5. **Integrity Check 2 (Link Check):** Query the row immediately preceding this one (by ID or timestamp) and verify its `record_hash` matches this row's `previous_hash`.
6. If all checks pass, the certificate is Valid and mathematically proven to be part of the blockchain.

### 4. Deletion/Revocation Flow (`delete_handler.php` & `view_certs.php`)
- The UI "Delete" button will be changed to "Revoke".
- The backend will perform a soft delete: `UPDATE certificates SET is_revoked = 1 WHERE id = ?`.
- This ensures the row remains in the database so subsequent blocks whose `previous_hash` points to it remain unbroken.
