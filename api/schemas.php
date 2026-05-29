<?php

// api/schemas.php - API pentru gestionarea schemelor
// Gestioneaza CRUD pentru schemele de campuri salvate de utilizatori
// Metode HTTP suportate: GET, POST, PUT, DELETE
// Depinde de: lib/Database.php, lib/FieldTypes.php

require_once __DIR__ . '/../config.php';

// Setam headerele pentru raspuns JSON
header('Content-Type: application/json; charset=UTF-8');

// Obtinem instanta bazei de date
$db = Database::getInstance();

// Obtinem metoda HTTP si actiunea din request
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// --------------------------------------------------
// Verificam autentificarea utilizatorului
// Toate operatiunile pe scheme necesita autentificare
// --------------------------------------------------
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Trebuie sa fii autentificat pentru a accesa schemele.'
    ]);
    exit;
}

// --------------------------------------------------
// Rutam cererea catre functia corespunzatoare
// in functie de metoda HTTP si actiune
// --------------------------------------------------
try {
    // GET /api/schemas.php - lista toate schemele utilizatorului
    // GET /api/schemas.php?action=get&id=X - returneaza o schema dupa id
    // GET /api/schemas.php?action=field_types - returneaza tipurile de campuri
    if ($method === 'GET') {
        if ($action === 'field_types') {
            getFieldTypes();
        } elseif ($action === 'get' && isset($_GET['id'])) {
            getSchema((int)$_GET['id'], $userId, $db);
        } else {
            getSchemas($userId, $db);
        }

    // POST /api/schemas.php - salveaza o schema noua
    } elseif ($method === 'POST') {
        $data = getRequestData();
        $act  = $data['action'] ?? 'save';
        if ($act === 'save') {
            saveSchema($data, $userId, $db);
        } else {
            jsonError('Actiune necunoscuta.');
        }

    // PUT /api/schemas.php?id=X - actualizeaza o schema existenta
    } elseif ($method === 'PUT') {
        $data = getRequestData();
        $id   = (int)($_GET['id'] ?? $data['id'] ?? 0);
        updateSchema($id, $data, $userId, $db);

    // DELETE /api/schemas.php?id=X - sterge o schema
    } elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        deleteSchema($id, $userId, $db);

    } else {
        http_response_code(405);
        jsonError('Metoda HTTP nu este suportata.');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Eroare server: ' . $e->getMessage()
    ]);
}

// ==================================================
// FUNCTII CRUD
// ==================================================

// --------------------------------------------------
// getFieldTypes() - returneaza toate tipurile de campuri
// Folosit de generator.js pentru dropdown-ul de selectie
// --------------------------------------------------
function getFieldTypes(): void
{
    echo json_encode([
        'success' => true,
        'types'   => FieldTypes::getAll()
    ]);
}

// --------------------------------------------------
// getSchemas() - returneaza toate schemele unui utilizator
// --------------------------------------------------
function getSchemas(int $userId, $db): void
{
    $schemas = $db->fetchAll(
        'SELECT id, name, fields_json, rows_count, created_at, updated_at
         FROM schemas
         WHERE user_id = ?
         ORDER BY updated_at DESC',
        [$userId]
    );

    // Decodam fields_json pentru fiecare schema
    foreach ($schemas as &$schema) {
        $schema['fields'] = json_decode($schema['fields_json'], true) ?? [];
        unset($schema['fields_json']);
    }

    echo json_encode([
        'success' => true,
        'schemas' => $schemas,
        'count'   => count($schemas)
    ]);
}

// --------------------------------------------------
// getSchema() - returneaza o schema dupa id
// Verifica ca schema apartine utilizatorului curent
// --------------------------------------------------
function getSchema(int $id, int $userId, $db): void
{
    if ($id <= 0) {
        jsonError('ID schema invalid.');
        return;
    }

    $schema = $db->fetchOne(
        'SELECT id, name, fields_json, rows_count, created_at, updated_at
         FROM schemas
         WHERE id = ? AND user_id = ?',
        [$id, $userId]
    );

    if (!$schema) {
        http_response_code(404);
        jsonError('Schema nu a fost gasita.');
        return;
    }

    // Decodam fields_json
    $schema['fields'] = json_decode($schema['fields_json'], true) ?? [];
    unset($schema['fields_json']);

    echo json_encode([
        'success' => true,
        'schema'  => $schema
    ]);
}

// --------------------------------------------------
// saveSchema() - salveaza o schema noua in baza de date
// --------------------------------------------------
function saveSchema(array $data, int $userId, $db): void
{
    // Validam datele primite
    $name       = trim($data['name'] ?? '');
    $fieldsJson = $data['fields_json'] ?? '[]';
    $rowsCount  = (int)($data['rows_count'] ?? DEFAULT_ROWS);

    if (empty($name)) {
        jsonError('Numele schemei este obligatoriu.');
        return;
    }

    // Validam fields_json
    $fields = json_decode($fieldsJson, true);
    if (!is_array($fields) || empty($fields)) {
        jsonError('Schema trebuie sa contina cel putin un camp.');
        return;
    }

    // Validam tipurile campurilor (securitate)
    $validTypes = array_keys(FieldTypes::getAll());
    foreach ($fields as $field) {
        if (!isset($field['type']) || !in_array($field['type'], $validTypes)) {
            jsonError('Tip de camp invalid: ' . ($field['type'] ?? 'necunoscut'));
            return;
        }
    }

    // Limitam numarul de randuri
    $rowsCount = max(1, min($rowsCount, MAX_ROWS));

    // Inseram schema in baza de date
    $schemaId = $db->insert('schemas', [
        'user_id'     => $userId,
        'name'        => sanitize($name),
        'fields_json' => json_encode($fields, JSON_UNESCAPED_UNICODE),
        'rows_count'  => $rowsCount
    ]);

    // Logam actiunea
    $db->log('save_schema', 'Schema salvata: ' . $name, $userId);

    echo json_encode([
        'success'   => true,
        'schema_id' => $schemaId,
        'message'   => 'Schema salvata cu succes!'
    ]);
}

// --------------------------------------------------
// updateSchema() - actualizeaza o schema existenta
// --------------------------------------------------
function updateSchema(int $id, array $data, int $userId, $db): void
{
    if ($id <= 0) {
        jsonError('ID schema invalid.');
        return;
    }

    // Verificam ca schema apartine utilizatorului
    $existing = $db->fetchOne(
        'SELECT id FROM schemas WHERE id = ? AND user_id = ?',
        [$id, $userId]
    );

    if (!$existing) {
        http_response_code(404);
        jsonError('Schema nu a fost gasita.');
        return;
    }

    // Pregatim datele de actualizat
    $updateData = ['updated_at' => date('Y-m-d H:i:s')];

    if (isset($data['name']) && !empty(trim($data['name']))) {
        $updateData['name'] = sanitize(trim($data['name']));
    }

    if (isset($data['fields_json'])) {
        $fields = json_decode($data['fields_json'], true);
        if (is_array($fields) && !empty($fields)) {
            $updateData['fields_json'] = json_encode($fields, JSON_UNESCAPED_UNICODE);
        }
    }

    if (isset($data['rows_count'])) {
        $updateData['rows_count'] = max(1, min((int)$data['rows_count'], MAX_ROWS));
    }

    // Actualizam in baza de date
    $db->update('schemas', $updateData, 'id = ? AND user_id = ?', [$id, $userId]);

    // Logam actiunea
    $db->log('update_schema', 'Schema actualizata: ID ' . $id, $userId);

    echo json_encode([
        'success' => true,
        'message' => 'Schema actualizata cu succes!'
    ]);
}

// --------------------------------------------------
// deleteSchema() - sterge o schema dupa id
// --------------------------------------------------
function deleteSchema(int $id, int $userId, $db): void
{
    if ($id <= 0) {
        jsonError('ID schema invalid.');
        return;
    }

    // Verificam ca schema apartine utilizatorului
    $existing = $db->fetchOne(
        'SELECT id, name FROM schemas WHERE id = ? AND user_id = ?',
        [$id, $userId]
    );

    if (!$existing) {
        http_response_code(404);
        jsonError('Schema nu a fost gasita.');
        return;
    }

    // Stergem schema
    $db->delete('schemas', 'id = ? AND user_id = ?', [$id, $userId]);

    // Logam actiunea
    $db->log('delete_schema', 'Schema stearsa: ' . $existing['name'], $userId);

    echo json_encode([
        'success' => true,
        'message' => 'Schema stearsa cu succes!'
    ]);
}

// ==================================================
// UTILITARE
// ==================================================

// --------------------------------------------------
// getRequestData() - citeste datele din body-ul cererii
// Suporta JSON si form-data
// --------------------------------------------------
function getRequestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    // Daca e JSON, decodam body-ul
    if (strpos($contentType, 'application/json') !== false) {
        $body = file_get_contents('php://input');
        return json_decode($body, true) ?? [];
    }

    // Altfel folosim $_POST
    return $_POST;
}

// --------------------------------------------------
// sanitize() - curata un string de caractere periculoase
// Previne XSS si SQL Injection
// --------------------------------------------------
function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// --------------------------------------------------
// jsonError() - trimite un raspuns de eroare JSON
// --------------------------------------------------
function jsonError(string $message): void
{
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
}
