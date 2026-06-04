<?php

// ==================================================
// api/templates.php - API pentru gestionarea sabloanelor
// Acest fisier primeste cereri AJAX si returneaza JSON
// Operatii disponibile: listare, creare, editare, stergere
// ==================================================

require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$authService = new AuthService();
$templateService = new TemplateService();

actionHandler();

function actionHandler()
{
    global $authService, $templateService;
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'list': handleList(); break;
            case 'get': handleGet(); break;
            case 'create': handleCreate(); break;
            case 'update': handleUpdate(); break;
            case 'delete': handleDelete(); break;
            default: jsonError('Actiune invalida!'); break;
        }
    } catch (Exception $e) {
        http_response_code(400);
        jsonError($e->getMessage());
    }
}

function handleList()
{
    global $templateService;
    jsonSuccess($templateService->listTemplates());
}

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

function handleCreate()
{
    if (empty($_POST)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $_POST = $input ?? [];
    }
    global $authService, $templateService;
    $authService->requireAuthentication();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $type = trim(htmlspecialchars($_POST['type'] ?? '', ENT_QUOTES, 'UTF-8'));
    $content = trim($_POST['content'] ?? '');
    $format = trim(htmlspecialchars($_POST['format'] ?? 'html', ENT_QUOTES, 'UTF-8'));

    $templateId = $templateService->createTemplate($name, $type, $content, $format, $authService->getEffectiveUserId());
    jsonSuccess(['id' => $templateId, 'message' => 'Sablonul a fost creat cu succes!']);
}

function handleUpdate()
{
    global $authService, $templateService;
    $authService->requireAuthentication();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    $input = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);
    $id = intval($input['id'] ?? 0); 
    if ($id <= 0) {
        jsonError('ID invalid!');
        return;
    }

    $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $content = trim($_POST['content'] ?? '');

    $templateService->updateTemplate($id, $name, $content);
    jsonSuccess(['message' => 'Sablonul a fost actualizat cu succes!']);
}

function handleDelete()
{
    global $authService, $templateService;
    $authService->requireAuthentication();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    $input = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);
    $id = intval($input['id'] ?? 0);   
    if ($id <= 0) {
        jsonError('ID invalid!');
        return;
    }

    $templateService->deleteTemplate($id);
    jsonSuccess(['message' => 'Sablonul a fost sters cu succes!']);
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