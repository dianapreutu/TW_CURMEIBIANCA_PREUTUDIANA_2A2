<?php

// ==================================================
// api/data.php - API pentru generarea si importul datelor
// Acest fisier primeste cereri AJAX si returneaza JSON
// Operatii: generare aleatorie, import CSV, CRUD schema
// ==================================================

require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$authService = new AuthService();
$dataService = new DataService();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'generate': handleGenerate(); break;
        case 'import_csv': handleImportCsv(); break;
        case 'get_types': handleGetTypes(); break;
        case 'save_schema': handleSaveSchema(); break;
        case 'list_schemas': handleListSchemas(); break;
        case 'delete_schema': handleDeleteSchema(); break;
        default: jsonError('Actiune invalida!'); break;
    }
} catch (Exception $e) {
    http_response_code(400);
    jsonError($e->getMessage());
}

function handleGenerate()
{
    global $dataService;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    $input = getRequestData();
    $fields = $input['fields'] ?? [];
    if (is_string($fields)) {
        $fields = json_decode($fields, true);
    }
    $count = (int)($input['rows'] ?? $input['count'] ?? DEFAULT_ROWS);

    if (empty($fields) || !is_array($fields)) {
        jsonError('Schema de campuri este invalida sau goala!');
        return;
    }

    if ($count <= 0 || $count > MAX_ROWS) {
        jsonError('Numarul de randuri trebuie sa fie intre 1 si ' . MAX_ROWS . '!');
        return;
    }

    $rows = $dataService->generateRows($fields, $count);
    $headers = array_keys($rows[0] ?? []);

    jsonSuccess([
        'headers' => $headers,
        'rows' => $rows,
        'row_count' => count($rows)
    ]);
}

function handleImportCsv()
{
    global $dataService, $authService;

    $authService->requireAuthentication();
    $userId = $authService->getEffectiveUserId();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    if (!isset($_FILES['csv_file'])) {
        jsonError('Nu a fost incarcat niciun fisier!');
        return;
    }

    try {
        $result = $dataService->importCsv($_FILES['csv_file'], $userId);
        jsonSuccess([
            'headers' => $result['headers'],
            'rows' => $result['rows'],
            'row_count' => $result['row_count'],
            'message' => 'CSV importat cu succes! ' . $result['row_count'] . ' randuri gasite.'
        ]);
    } catch (Exception $e) {
        jsonError('Eroare la importul CSV: ' . $e->getMessage());
    }
}

function handleGetTypes()
{
    $types = FieldTypes::getAll();
    $result = [];

    foreach ($types as $key => $label) {
        $result[] = [
            'type' => $key,
            'label' => $label,
            'description' => FieldTypes::describe($key)
        ];
    }

    jsonSuccess($result);
}

function handleSaveSchema()
{
    global $dataService, $authService;

    $authService->requireAuthentication();
    $data = getRequestData();
    $name = trim($data['name'] ?? '');
    $fields = $data['fields'] ?? json_decode($data['fields_json'] ?? '[]', true);

    if (is_string($fields)) {
        $fields = json_decode($fields, true);
    }

    $schemaId = $dataService->saveSchema($name, $fields, $authService->getEffectiveUserId());
    jsonSuccess(['id' => $schemaId, 'message' => 'Schema a fost salvata cu succes!']);
}

function handleListSchemas()
{
    global $dataService, $authService;

    $authService->requireAuthentication();
    $userId = $authService->getEffectiveUserId();
    $schemas = $dataService->listSchemas($userId);
    jsonSuccess($schemas);
}

function handleDeleteSchema()
{
    global $dataService, $authService;

    $authService->requireAuthentication();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonError('ID invalid!');
        return;
    }

    $dataService->deleteSchema($id, $authService->getEffectiveUserId());
    jsonSuccess(['message' => 'Schema a fost stearsa cu succes!']);
}

function jsonSuccess($data)
{
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message)
{
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function getRequestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $body = file_get_contents('php://input');
        return json_decode($body, true) ?? [];
    }
    return $_POST;
}
