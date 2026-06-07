<?php

// api/templates.php
// API pentru gestionarea sabloanelor de documente
// Primeste cereri AJAX si returneaza raspunsuri JSON
// Operatii disponibile: listare, obtinere, creare, editare, stergere

require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$authService     = new AuthService();
$templateService = new TemplateService();

actionHandler();

// Rutam actiunea catre handler-ul corespunzator
function actionHandler()
{
    global $authService, $templateService;
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'list':   handleList();   break;
            case 'get':    handleGet();    break;
            case 'create': handleCreate(); break;
            case 'update': handleUpdate(); break;
            case 'delete': handleDelete(); break;
            default:       jsonError('Actiune invalida!'); break;
        }
    } catch (Exception $e) {
        http_response_code(400);
        jsonError($e->getMessage());
    }
}

// Returneaza lista tuturor sabloanelor disponibile
function handleList()
{
    global $templateService;
    jsonSuccess($templateService->listTemplates());
}

// Returneaza un sablon specific dupa ID
function handleGet()
{
    global $templateService;

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonError('ID invalid!');
        return;
    }

    $template = $templateService->getTemplate($id);
    if (!$template) {
        jsonError('Sablonul nu a fost gasit!');
        return;
    }

    jsonSuccess($template);
}

// Creeaza un sablon nou pe baza datelor trimise
function handleCreate()
{
    global $authService, $templateService;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    // Citim input-ul o singura data - ajaxPost trimite Content-Type: application/json
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);

    // Fallback la $_POST daca nu e JSON valid (ex: FormData)
    if (!is_array($input)) {
        $input = $_POST;
    }

    $name    = trim(htmlspecialchars($input['name']    ?? '', ENT_QUOTES, 'UTF-8'));
    $type    = trim(htmlspecialchars($input['type']    ?? '', ENT_QUOTES, 'UTF-8'));
    $content = trim($input['content'] ?? '');
    $format  = trim(htmlspecialchars($input['format']  ?? 'html', ENT_QUOTES, 'UTF-8'));

    $templateId = $templateService->createTemplate(
        $name, $type, $content, $format,
        $authService->getEffectiveUserId()
    );

    jsonSuccess(['id' => $templateId, 'message' => 'Sablonul a fost creat cu succes!']);
}

// Actualizeaza numele si continutul unui sablon existent
function handleUpdate()
{
    global $authService, $templateService;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    // Citim input-ul o singura data, inainte de orice validare
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $id = intval($input['id'] ?? 0);
    if ($id <= 0) {
        jsonError('ID invalid!');
        return;
    }

    $name    = trim(htmlspecialchars($input['name']    ?? '', ENT_QUOTES, 'UTF-8'));
    $content = trim($input['content'] ?? '');

    $templateService->updateTemplate($id, $name, $content);
    jsonSuccess(['message' => 'Sablonul a fost actualizat cu succes!']);
}

// Sterge un sablon dupa ID - operatie permisa doar administratorului
function handleDelete()
{
    global $authService, $templateService;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    // Citim input-ul o singura data, inainte de orice validare
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    if (!$authService->isAdmin()) {
        throw new Exception('Doar administratorul poate sterge sabloane!');
    }

    $id = intval($input['id'] ?? 0);
    if ($id <= 0) {
        jsonError('ID invalid!');
        return;
    }

    $templateService->deleteTemplate($id);
    jsonSuccess(['message' => 'Sablonul a fost sters cu succes!']);
}

// Returneaza un raspuns JSON de succes si opreste executia
function jsonSuccess($data)
{
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// Returneaza un raspuns JSON de eroare si opreste executia
function jsonError($message)
{
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}