<?php

// ==================================================
// api/data.php - API pentru generarea si importul datelor
// Acest fisier primeste cereri AJAX si returneaza JSON
// Operatii: generare aleatorie, import CSV
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