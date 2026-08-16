ALTER TABLE certificates DROP INDEX document_hash;
ALTER TABLE audit_log MODIFY COLUMN action ENUM('issue','verify','revoke','login','login_failed') NOT NULL;
