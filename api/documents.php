<?php

// ==================================================
// api/documents.php - API pentru generarea documentelor 
// Acest fisier primeste cereri AJAX si returneaza JSON
// Operatii disponibile: generare, listare, stergere
// ==================================================

require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$authService = new AuthService();
$documentService = new DocumentService();

actionHandler($authService, $documentService);

function actionHandler(AuthService $authService, DocumentService $documentService)
{
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'list': handleList($authService, $documentService); break;
            case 'generate': handleGenerate($authService, $documentService); break;
            case 'get': handleGet($authService, $documentService); break;
            case 'delete': handleDelete($authService, $documentService); break;
            default: jsonError('Actiune invalida!'); break;
        }
    } catch (Exception $e) {
        http_response_code(400);
        jsonError($e->getMessage());
    }
}

function handleList(AuthService $authService, DocumentService $documentService)
{
    $userId = $authService->getEffectiveUserId();
    if (!$userId) {
        http_response_code(401);
        jsonError('Trebuie sa fii autentificat pentru a accesa aceasta resursa.');
        return;
    }

    jsonSuccess($documentService->listDocuments($userId, $authService->isAdmin()));
}

function handleGet(AuthService $authService, DocumentService $documentService)
{
    $userId = $authService->getEffectiveUserId();
    if (!$userId) {
        http_response_code(401);
        jsonError('Trebuie sa fii autentificat pentru a accesa aceasta resursa.');
        return;
    }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonError('ID invalid!');
        return;
    }

    $document = $documentService->getDocument($id, $userId, $authService->isAdmin());
    if (!$document) {
        jsonError('Documentul nu a fost gasit!');
        return;
    }

    jsonSuccess($document);
}

function handleGenerate(AuthService $authService, DocumentService $documentService)
{
    $authService->requireAuthentication();
    $userId = $authService->getEffectiveUserId();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    $templateId = intval($_POST['template_id'] ?? 0);
    $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $dataSource = trim(htmlspecialchars($_POST['data_source'] ?? 'random', ENT_QUOTES, 'UTF-8'));
    $count = intval($_POST['count'] ?? DEFAULT_ROWS);

    if ($templateId <= 0) {
        jsonError('ID-ul sablonului este invalid!');
        return;
    }

    if (empty($name)) {
        jsonError('Numele documentului este obligatoriu!');
        return;
    }

    if (!in_array($dataSource, ['random', 'csv'], true)) {
        jsonError('Sursa de date invalida! Folositi random sau csv');
        return;
    }

    if ($dataSource === 'csv') {
        $data = handleCSVData();
        if ($data === null) {
            return;
        }
    } else {
        $fieldsJson = $_POST['fields'] ?? '[]';
        $fields = json_decode($fieldsJson, true);
        if (empty($fields)) {
            $fields = [
                ['field' => 'nume', 'type' => 'full_name', 'label' => 'Nume'],
                ['field' => 'email', 'type' => 'email', 'label' => 'Email'],
                ['field' => 'data', 'type' => 'date', 'label' => 'Data']
            ];
        }

        $data = (new DataGenerator())->generate($fields, 1)[0];
    }

    try {
        $result = $documentService->generateDocument((int)$templateId, $data, $name, $userId);
        jsonSuccess([
            'id' => $result['id'],
            'filename' => $result['filename'],
            'html' => $result['html'],
            'message' => 'Documentul a fost generat cu succes!'
        ]);
    } catch (Exception $e) {
        jsonError('Eroare la generarea documentului: ' . $e->getMessage());
    }
}

function handleDelete(AuthService $authService, DocumentService $documentService)
{
    $userId = $authService->getEffectiveUserId();
    if (!$userId) {
        http_response_code(401);
        jsonError('Trebuie sa fii autentificat pentru a accesa aceasta resursa.');
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonError('ID invalid!');
        return;
    }

    try {
        $documentService->deleteDocument($id, $userId, $authService->isAdmin());
        jsonSuccess(['message' => 'Documentul a fost sters cu succes!']);
    } catch (Exception $e) {
        jsonError($e->getMessage());
    }
}

function handleCSVData()
{
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        jsonError('Nu a fost incarcat niciun fisier CSV valid!');
        return null;
    }

    $file = $_FILES['csv_file'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        jsonError('Fisierul trebuie sa fie de tip CSV!');
        return null;
    }

    $csvHandler = new CsvHandler(Database::getInstance());
    try {
        $result = $csvHandler->handleUpload($file, 1);
        if (empty($result['rows'])) {
            jsonError('CSV-ul nu contine randuri valide.');
            return null;
        }

        return $result['rows'][0];
    } catch (Exception $e) {
        jsonError('Eroare la importul CSV: ' . $e->getMessage());
        return null;
    }
}

function jsonSuccess($data)
{
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message)
{
    echo json_encode([
        'success' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
