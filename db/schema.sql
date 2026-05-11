PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('admin', 'user')),
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    last_login TEXT
);

CREATE TABLE IF NOT EXISTS templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    description TEXT,
    fields_json TEXT NOT NULL DEFAULT '[]',
    filename TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS schemas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    fields_json TEXT NOT NULL DEFAULT '[]',
    rows_count INTEGER NOT NULL DEFAULT 10 CHECK(rows_count > 0 AND rows_count <= 1000),
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    template_id INTEGER REFERENCES templates(id) ON DELETE SET NULL,
    schema_id INTEGER REFERENCES schemas(id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    html_path TEXT,
    pdf_path TEXT,
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft', 'generated', 'exported')),
    rows_count INTEGER NOT NULL DEFAULT 10,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS exports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    format TEXT NOT NULL CHECK(format IN ('html', 'pdf', 'csv', 'json')),
    file_path TEXT,
    exported_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    description TEXT,
    entity TEXT,
    entity_id INTEGER,
    ip_address TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS csv_imports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    original_name TEXT NOT NULL,
    file_path TEXT NOT NULL,
    row_count INTEGER,
    headers_json TEXT,
    uploaded_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX IF NOT EXISTS idx_schemas_user ON schemas(user_id);
CREATE INDEX IF NOT EXISTS idx_documents_user ON documents(user_id);
CREATE INDEX IF NOT EXISTS idx_documents_tmpl ON documents(template_id);
CREATE INDEX IF NOT EXISTS idx_exports_doc ON exports(document_id);
CREATE INDEX IF NOT EXISTS idx_logs_user ON logs(user_id);
CREATE INDEX IF NOT EXISTS idx_logs_action ON logs(action);
CREATE INDEX IF NOT EXISTS idx_csv_user ON csv_imports(user_id);

INSERT OR IGNORE INTO users (username, email, password, role)
VALUES (
    'admin',
    'admin@docgen.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin'
);

INSERT OR IGNORE INTO templates (name, label, description, filename, fields_json)
VALUES (
    'cv',
    'Curriculum Vitae',
    'Sablon pentru generarea unui CV cu date realiste',
    'cv.json',
    '[
        {"field":"nume","type":"full_name","label":"Nume complet"},
        {"field":"email","type":"email","label":"Adresa email"},
        {"field":"telefon","type":"phone","label":"Numar telefon"},
        {"field":"adresa","type":"address","label":"Adresa"},
        {"field":"data_nasterii","type":"date","label":"Data nasterii"},
        {"field":"cnp","type":"cnp","label":"CNP"},
        {"field":"ocupatie","type":"job_title","label":"Ocupatie"},
        {"field":"studii","type":"education","label":"Nivel studii"}
    ]'
);

INSERT OR IGNORE INTO templates (name, label, description, filename, fields_json)
VALUES (
    'cerere',
    'Cerere',
    'Sablon pentru o cerere administrativa generica',
    'cerere.json',
    '[
        {"field":"nume_solicitant","type":"full_name","label":"Nume solicitant"},
        {"field":"cnp","type":"cnp","label":"CNP"},
        {"field":"adresa","type":"address","label":"Adresa"},
        {"field":"data","type":"date","label":"Data cererii"},
        {"field":"subiect","type":"text","label":"Subiect cerere"},
        {"field":"detalii","type":"paragraph","label":"Detalii"}
    ]'
);

INSERT OR IGNORE INTO templates (name, label, description, filename, fields_json)
VALUES (
    'factura',
    'Factura',
    'Sablon pentru o factura fiscala simpla',
    'factura.json',
    '[
        {"field":"nr_factura","type":"invoice_number","label":"Numar factura"},
        {"field":"data_emitere","type":"date","label":"Data emiterii"},
        {"field":"furnizor","type":"company","label":"Furnizor"},
        {"field":"cui_furnizor","type":"cui","label":"CUI furnizor"},
        {"field":"client","type":"company","label":"Client"},
        {"field":"cui_client","type":"cui","label":"CUI client"},
        {"field":"produs","type":"product","label":"Produs/Serviciu"},
        {"field":"cantitate","type":"number","label":"Cantitate"},
        {"field":"pret_unitar","type":"price","label":"Pret unitar (RON)"},
        {"field":"tva","type":"tva","label":"TVA (%)"}
    ]'
);