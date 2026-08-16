CREATE TABLE certificates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_hash TEXT NOT NULL,
    previous_hash TEXT DEFAULT NULL UNIQUE,
    record_hash TEXT NOT NULL,
    is_revoked INTEGER NOT NULL DEFAULT 0,
    student_name TEXT NOT NULL,
    degree TEXT NOT NULL,
    institution TEXT NOT NULL,
    issuance_date TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE institutions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    location TEXT NOT NULL,
    email TEXT NOT NULL,
    website TEXT NOT NULL,
    rep_name TEXT NOT NULL,
    rep_title TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'active')),
    failed_attempts INTEGER NOT NULL DEFAULT 0,
    locked_until TEXT DEFAULT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    institution TEXT,
    action TEXT NOT NULL CHECK (action IN ('issue', 'verify', 'revoke', 'login', 'login_failed')),
    document_hash TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Seeded demo accounts (passwords: nosh327 / brac327)
INSERT OR IGNORE INTO institutions (name, password_hash, location, email, website, rep_name, rep_title, status) VALUES
('North South University', '$2y$10$WpIw16WDZXSZczxNjiEJ5.sPT4WEgjm8yCl1zq1mWd/jEh3jf.Qwy', 'Dhaka, Bangladesh', 'registrar@northsouth.edu', 'https://www.northsouth.edu', 'Demo Registrar', 'Registrar', 'active'),
('Brac University', '$2y$10$3JC7t1a1L9ZPt/yUeCi48.SKoE2E7.fupleNoCny5.jMGcfuGKkWm', 'Dhaka, Bangladesh', 'registrar@bracu.ac.bd', 'https://www.bracu.ac.bd', 'Demo Registrar', 'Registrar', 'active');
