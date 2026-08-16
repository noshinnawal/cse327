-- Migration: Add blockchain fields and rename hash to document_hash
-- Compatible with MySQL/MariaDB

ALTER TABLE certificates
    RENAME COLUMN hash TO document_hash,
    ADD COLUMN previous_hash VARCHAR(64) DEFAULT NULL UNIQUE AFTER document_hash,
    ADD COLUMN record_hash VARCHAR(64) NOT NULL AFTER previous_hash,
    ADD COLUMN is_revoked BOOLEAN NOT NULL DEFAULT 0 AFTER record_hash;

ALTER TABLE audit_log
    RENAME COLUMN hash TO document_hash;
