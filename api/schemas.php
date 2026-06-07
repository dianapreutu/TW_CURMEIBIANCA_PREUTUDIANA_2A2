<?php

// api/schemas.php
// API pentru gestionarea schemelor de campuri salvate de utilizatori
// Metode HTTP suportate: GET, POST, PUT, DELETE

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=UTF-8');

$authService   = new AuthService();
$schemaService = new SchemaService();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Rutam cererea catre functia corespunzatoare in functie de metoda HTTP si actiune
try {
    if ($method === 'GET') {
        if ($action === 'field_types') {
            getFieldTypes();
        } elseif ($action === 'get' && isset($_GET['id'])) {
            getSchema((int)$_GET['id']);
        } else {
            getSchemas();
        }
    } elseif ($method === 'POST') {
        $data = getRequestData();
        saveSchema($data);
    } elseif ($method === 'PUT') {
        $data = getRequestData();
        $id   = (int)($_GET['id'] ?? $data['id'] ?? 0);
        updateSchema($id, $data);
    } elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        deleteSchema($id);
    } else {
        http_response_code(405);
        jsonError('Metoda HTTP nu este suportata.');
    }
} catch (Exception $e) {
    http_response_code(400);
    jsonError('Eroare: ' . $e->getMessage());
}

// Returneaza lista tipurilor de campuri disponibile
function getFieldTypes(): void
{
    echo json_encode([
        'success' => true,
        'data'    => ['types' => FieldTypes::getAll()]
    ], JSON_UNESCAPED_UNICODE);
}

// Returneaza toate schemele apartinand utilizatorului curent
function getSchemas(): void
{
    global $schemaService, $authService;

    $authService->requireAuthentication();
    $userId = $authService->getEffectiveUserId();

    jsonSuccess(['schemas' => $schemaService->getSchemas($userId)]);
}

// Returneaza o schema specifica dupa ID
function getSchema(int $id): void
{
    global $schemaService, $authService;

    $authService->requireAuthentication();
    $schema = $schemaService->getSchema($id, $authService->getEffectiveUserId());

    if (!$schema) {
        http_response_code(404);
        jsonError('Schema nu a fost gasita.');
        return;
    }

    jsonSuccess(['schema' => $schema]);
}

// Salveaza o schema noua pentru utilizatorul curent
function saveSchema(array $data): void
{
    global $schemaService, $authService;

    $authService->requireAuthentication();

    $name      = trim($data['name'] ?? '');
    $fields    = $data['fields'] ?? json_decode($data['fields_json'] ?? '[]', true);
    $rowsCount = (int)($data['rows_count'] ?? DEFAULT_ROWS);

    $schemaId = $schemaService->saveSchema($name, $fields, $authService->getEffectiveUserId());
    jsonSuccess(['schema_id' => $schemaId, 'message' => 'Schema salvata cu succes!']);
}

// Actualizeaza o schema existenta dupa ID
function updateSchema(int $id, array $data): void
{
    global $schemaService, $authService;

    $authService->requireAuthentication();

    $fields = $data['fields'] ?? json_decode($data['fields_json'] ?? '[]', true);
    if (is_string($fields)) {
        $fields = json_decode($fields, true);
    }

    $updateData = ['name' => trim($data['name'] ?? '')];

    if (!empty($fields)) {
        $updateData['fields_json'] = json_encode($fields, JSON_UNESCAPED_UNICODE);
    }

    if (isset($data['rows_count'])) {
        $updateData['rows_count'] = max(1, min((int)$data['rows_count'], MAX_ROWS));
    }

    $schemaService->updateSchema($id, $updateData, $authService->getEffectiveUserId());
    jsonSuccess(['message' => 'Schema actualizata cu succes!']);
}

// Sterge o schema dupa ID
function deleteSchema(int $id): void
{
    global $schemaService, $authService;

    $authService->requireAuthentication();
    $schemaService->deleteSchema($id, $authService->getEffectiveUserId());

    jsonSuccess(['message' => 'Schema stearsa cu succes!']);
}

// Citeste datele din cerere - suporta JSON body si form POST clasic
function getRequestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $body = file_get_contents('php://input');
        return json_decode($body, true) ?? [];
    }
    return $_POST;
}

// Returneaza un raspuns JSON de succes
function jsonSuccess($data): void
{
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
}

// Returneaza un raspuns JSON de eroare
function jsonError(string $message): void
{
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
}