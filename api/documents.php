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
$dataService = new DataService();
$templateService = new TemplateService();

actionHandler($authService, $documentService, $dataService);

function actionHandler(AuthService $authService, DocumentService $documentService, DataService $dataService)
{
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'list': handleList($authService, $documentService); break;
            case 'generate': handleGenerate($authService, $documentService, $dataService); break;
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

function handleGenerate(AuthService $authService, DocumentService $documentService, DataService $dataService)
{
    global $templateService; // adaugat pentru a accesa campurile sablonului

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    // Citim input-ul O SINGURA DATA, inainte de orice altceva
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $userId = $authService->getEffectiveUserId() ?? 1;

    $templateId = intval($input['template_id'] ?? 0);
    $name = trim(htmlspecialchars($input['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $dataSource = trim(htmlspecialchars($input['data_source'] ?? 'random', ENT_QUOTES, 'UTF-8'));

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

    try {
        if ($dataSource === 'csv') {
            $data = $dataService->parseCsvRow($_FILES['csv_file'] ?? []);
        } else {
            // Citim campurile din sablon pentru a genera date corespunzatoare
            $template = $templateService->getTemplate($templateId);
            $fields = [];

            if ($template && !empty($template['fields_json'])) {
                $decoded = json_decode($template['fields_json'], true);
                if (is_array($decoded)) {
                    // Sablon predefinit cu array de campuri — folosim campurile lui
                    $fields = $decoded;
                }
            }

            // Daca nu avem campuri din sablon (sablon HTML custom),
            // folosim toate campurile generice disponibile in toolbar
            if (empty($fields)) {
                $fields = [
                    ['field' => 'nume',        'type' => 'full_name',     'label' => 'Nume'],
                    ['field' => 'email',       'type' => 'email',         'label' => 'Email'],
                    ['field' => 'telefon',     'type' => 'phone',         'label' => 'Telefon'],
                    ['field' => 'adresa',      'type' => 'address',       'label' => 'Adresa'],
                    ['field' => 'cnp',         'type' => 'cnp',           'label' => 'CNP'],
                    ['field' => 'firma',       'type' => 'company',       'label' => 'Firma'],
                    ['field' => 'nr_factura',  'type' => 'invoice_number','label' => 'Numar factura'],
                    ['field' => 'suma',        'type' => 'price',         'label' => 'Suma'],
                    ['field' => 'data',        'type' => 'date',          'label' => 'Data'],
                ];
            }

            $data = $dataService->generateRecord($fields);
        }

        $result = $documentService->generateDocument((int)$templateId, $data, $name, $userId);
        jsonSuccess([
            'id'       => $result['id'],
            'filename' => $result['filename'],
            'html'     => $result['html'],
            'message'  => 'Documentul a fost generat cu succes!'
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
