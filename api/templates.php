<?php 

// ==================================================
// api/templates.php - API pentru gestionarea sabloanelor
// Acest fisier primeste cereri AJAX si returneaza JSON
// Operatii disponibile: listare, creare, editare, stergere 
// ==================================================

// Includem configurarile globale
require_once '../config.php';

// Setam header-ul pentru raspuns JSON
// Browser-ul va sti ca primeste date JSON, nu HTML
header('Content-Type: application/json; charset=utf-8');

// Initializam motorul de templating
// Acesta include si conexiunea la baza de date 
$engine = new TemplateEngine();

// Citim actiunea ceruta din parametrii GET sau POST
// Ex: ?action=list sau ?action=create
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Rutam cererea catre functia corespunzatoare actiunii
switch ($action) {
    case 'list': handleList($engine);       break;
    case 'get': handleGet($engine);     break;
    case 'create': handleCreate($engine);       break;
    case 'update': handleUpdate($engine);       break;
    case 'delete': handleDelete($engine);       break;
    default: jsonError('Actiune invalida!');        break;  
}

// ==================================================
// handleList() - returneaza toate sabloanele din DB
// Cerere: GET api/templates.php?action=list
// ==================================================
function handleList(TemplateEngine $engine)
{
    // Obtinem toate sabloanele din baza de date
    $templates = $engine->getAllTemplates();

    // Returnam lista ca JSON
    jsonSuccess($templates);
}

// ==================================================
// handleGet() - returneaza un sablon dupa ID
// Cerere: GET api/templates.php?action=get&id=1
// ==================================================
function handleGet(TemplateEngine $engine) 
{
    // Citim si validam ID-ul din parametrii GET
    // intval() converteste la intreg, prevenind SQL Injection
    $id = intval($_GET['id'] ?? 0);

    // Verificam ca ID-ul este valid
    if ($id <= 0) {
        jsonError('ID invalid!');
        return;
    }

    // Cautam sablonul in baza de date
    $template = $engine->loadTemplate($id);

    // Daca nu a fost gasit, returnam eroare
    if (!$template) {
        jsonError('Sablonul nu a fost gasit!');
        return;
    }

    // Returnam sablonul gasit
    jsonSuccess($template);
}

// ==================================================
// handleCreate() - creeaza un sablon nou
// Cerere: POST api/templates.php?action=create
// Body: name, type, content, format
// ==================================================
function handleCreate(TemplateEngine $engine)
{
    // Verificam ca cererea este de tip POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    // Citim si curatam datele trimise din formular
    // htmlspecialchars() previne atacurile XSS
    $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $type = trim(htmlspecialchars($_POST['type'] ?? '', ENT_QUOTES, 'UTF-8'));
    $content = trim($_POST['content'] ?? '');
    $format = trim(htmlspecialchars($_POST['format'] ?? 'html', ENT_QUOTES, 'UTF-8'));

    // Validam campurile obligatorii
    if (empty($name) || empty($type) || empty($content)) {
        jsonError('Campurile nume, tip si continut sunt obligatorii!');
        return;
    }

    // Validam tipul sablonului (doar valori permise)
    $allowedTypes = ['cv', 'cerere', 'factura', 'catalog', 'alt'];
    if (!in_array($type, $allowedTypes)) {
        jsonError('Tipul de sablon este invalid!');
        return;
    }

    // Validam formatul (doar html sau json)
    if (!in_array($format, ['html', 'json'])) {
        jsonError('Formatul trebuie sa fie html sau json!');
        return;
    }
    
    // Salvam sablonul in baza de date
    $id = $engine->saveTemplate($name, $type, $content, $format);

    // Returnam succes cu ID-ul sablonului creat
    jsonSuccess(['id' => $id, 'message' => 'Sablonul a fost creat cu succes!']);
}

// ==================================================
// handleUpdate() - actualizeaza un sablon existent
// Cerere: POST api/templates.php?action=update
// Body: id, name, content
// ==================================================
function handleUpdate(TemplateEngine $engine)
{
    // Verificam ca cererea este de tip POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    // Citim si validam ID-ul
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonError('ID invalid!');
        return;
    }

    // Citim si curatam noile date
    $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $content = trim($_POST['content'] ?? '');

    // Validam campurile obligatorii
    if (empty($name) || empty($content)) {
        jsonError('Campurile nume si continut sunt obligatorii!');
        return;
    }

    // Verificam ca sablonul exista
    $existing = $engine->loadTemplate($id);
    if (!$existing) {
        jsonError('Sablonul nu a fost gasit!');
        return;
    }

    // Actualizam sablonul in baza de date
    $engine->updateTemplate($id, $name, $content);

    // Returnam succes
    jsonSuccess(['message' => 'Sablonul a fost actualizat cu succes!']);
}

// ==================================================
// handleDelete() - sterge un sablon
// Cerere: POST api/templates.php?action=delete
// Body: id
// ==================================================
function handleDelete(TemplateEngine $engine)
{
    // Verificam ca cererea este de tip POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    // Citim si validam ID-ul
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonError('ID invalid!');
        return;
    }

    // Verificam ca sablonul exista inainte de stergere
    $existing = $engine->loadTemplate($id);
    if (!$existing) {
        jsonError('Sablonul nu a fost gasit!');
        return;
    }

    // Stergem sablonul din baza de date
    $engine->deleteTemplate($id);

    // Returnam succes
    jsonSuccess(['message' => 'Sablonul a fost sters cu succes!']);
}

// ==================================================
// jsonSuccess() - trimite un raspuns JSON de succes
// $data - datele de returnat
// ==================================================
function jsonSuccess($data) 
{
    echo json_encode([
        'success' => true,
        'data' => $data 
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================================================
// jsonError() - trimite un raspuns JSON de eroare
// $message - mesajul de eroare
// ==================================================
function jsonError($message)
{
    echo json_encode([
        'success' => false,
        'error' => $message 
    ], JSON_UNESCAPED_UNICODE);
    exit;
}