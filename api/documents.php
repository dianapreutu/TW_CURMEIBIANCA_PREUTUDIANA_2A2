<?php

// ==================================================
// api/documents.php - API pentru generarea documentelor 
// Acest fisier primeste cereri AJAX si returneaza JSON
// Operatii disponibile: generare, listare, stergere
// ==================================================

// Includem configurarile globale
require_once '../config.php';

// Setam header-ul pentru raspuns JSON
header('Content-Type: application/json; charset=utf-8');

// Initializam clasele necesare
$engine = new TemplateEngine();
$generator = new DataGenerator();
$db = Database::getInstance();

// Citim actiunea ceruta din parametrii GET sau POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Rutam cererea catre functia corespunzatoare
switch ($action) {
    case 'list': handleList($db);       break;
    case 'generate': handleGenerate($engine, $generator);       break;
    case 'get': handleGet($db);     break;
    case 'delete': handleDelete($db);       break;
    default: jsonError('Actiune invalida!');        break;
}

// ==================================================
// handleList() - returneaza toate documentele generate
// Cerere: GET api/documents.php?action=list
// ==================================================
function handleList(Database $db) 
{
    // Selectam toate documentele ordonate dupa data crearii
    $documents = $db->fetchAll(
        'SELECT d.*, t.name as template_name
        FROM documents d
        LEFT JOIN templates t ON d.template_id = t.id
        ORDER BY d.created_at DESC'
    );

    // Returnam lista de documente
    jsonSuccess($documents);
}

// ==================================================
// handleGet() - returneaza un document dupa ID
// Cerere: GET api/documents.php?action=get&id=1
// ==================================================
function handleGet(Database $db)
{
    // Citim si validam ID-ul 
    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonError('ID invalid!');
        return;
    }

    // Cautam documentul in baza de date
    $document = $db->fetchOne(
        'SELECT d.*, t.name as template_name
        FROM documents d
        LEFT JOIN templates t ON d.template_id = t.id
        WHERE d.id = ?',
        [$id]
    );

    // Daca nu a fost gasit, returnam eroare
    if (!$document) {
        jsonError('Documentul nu a fost gasit!');
        return;
    }

    // Citim si continutul fisierului HTML generat
    $filePath = GENERATED_HTML_PATH . '/' . $document['file_path'];
    if (file_exists($filePath)) {
        // Adaugam continutul HTML la raspuns 
        $document['html_content'] = file_get_contents($filePath);
    }

    // Returnam documentul
    jsonSuccess($document);
}

// ==================================================
// handleGenerate() - genereaza un document nou
// Cerere: POST api/documents.php?action=generate
// Body: template_id, name, data_source, count, data (optional)
// ==================================================
function handleGenerate(TemplateEngine $engine, DataGenerator $generator)
{
    // Verificam ca cererea este de tip POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    // Citim si validam datele trimise
    $templateId = intval($_POST['template_id'] ?? 0);
    $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $dataSource = trim(htmlspecialchars($_POST['data_source'] ?? 'random', ENT_QUOTES, 'UTF-8'));
    $count = intval($_POST['count'] ?? DEFAULT_ROWS);

    // Validam campurile obligatorii
    if ($templateId <= 0) {
        jsonError('ID-ul sablonului este invalid!');
        return;
    }

    if (empty($name)) {
        jsonError('Numele documentului este obligatoriu!');
        return;
    }

    // Validam sursa de date
    if (!in_array($dataSource, ['random', 'csv'])) {
        jsonError('Sursa de date invalida! Folositi random sau csv');
        return;
    }

    // Obtinem datele in functie de sursa aleasa
    if ($dataSource === 'csv') {
        // Datele vin dintr-un fisier CSV incarcat
        $data = handleCSVData();

        // Daca nu s-au putut citi datele din CSV, oprim
        if ($data === null) {
            return;
        }
    } else {
        // Generam date aleatorii
        // Citim schema de campuri din POST (trimisa ca JSON)
        $fieldsJson = $_POST['fields'] ?? '[]';
        $fields = json_decode($fieldsJson, true);

        // Daca nu avem campuri definite, folosim un set implicit
        if (empty($fields)) {
            $fields = [
                ['name' => 'nume', 'type' => 'full_name'],
                ['name' => 'email', 'type' => 'email'],
                ['name' => 'data', 'type' => 'date']
            ];
        }

        // Generam un singur rand de date pentru document
        $data = $generator->generate($fields, 1)[0];
    }

    // Generam documentul folosind TemplateEngine
    try {
        $result = $engine->generateDocument($templateId, $data, $name);

        // Returnam succes cu detaliile documentului generat
        jsonSuccess([
            'id' => $result['id'],
            'filename' => $result['filename'],
            'html' => $result['html'],
            'message' => 'Documentul a fost generat cu succes!'
        ]);

    } catch (Exception $e) {
        // Daca generarea a esuat, returnam eroarea
        jsonError('Eroare la generarea documentului: ' . $e->getMessage());
    }
}

// ==================================================
// handleCSVData() - citeste datele dintr-un fisier CSV incarcat
// Returneaza primul rand de date din CSV sau null la eroare
// ==================================================
function handleCSVData()
{
    // Verificam daca a fost incarcat un fisier CSV
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        jsonError('Nu a fost incarcat niciun fisier CSV valid!');
        return null;
    }

    $file = $_FILES['csv_file'];

    // Verificam extensia fisierului (doar .csv permis)
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        jsonError('Fisierul trebuie sa fie de tip CSV!');
        return null;
    }

    // Citim continutul fisierului CSV
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        jsonError('Nu s-a putut citi fisierul CSV!');
        return null;
    }

    // Citim header-ul (prima linie cu numele coloanelor)
    $headers = fgetcsv($handle, 0, ',');

    // Citim primul rand de date
    $row = fgetcsv($handle, 0, ',');

    // Inchidem fisierul
    fclose($handle);

    // Verificam ca am citit date valide
    if (!$headers || !$row) {
        jsonError('Fisierul CSV este gol sau invalid!');
        return null;
    }

    // Combinam headerele cu valorile intr-un array asociativ
    // ex: ['nume' => 'Ion Popescu', 'email' => 'ion@gmail.com']
    $data = array_combine($headers, $row);

    return $data;
}

// ==================================================
// handleDelete() - sterge un document generat
// Cerere: POST api/documents.php?action=delete
// Body: id
// ==================================================
function handleDelete(Database $db) 
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

    // Cautam documentul in baza de date
    $document = $db->fetchOne(
        'SELECT * FROM documents WHERE id = ?',
        [$id]
    );

    if (!$document) {
        jsonError('Documentul nu a fost gasit!');
        return;
    }

    // Stergem fisierul HTML de pe server (daca exista)
    $filePath = GENERATED_HTML_PATH . '/' . $document['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Stergem inregistrarea din baza de date
    $db->delete('documents', 'id = ?', [$id]);

    // Inregistram actiunea in logs
    $db->log('delete_document', "Document sters ID: {$id}");

    // Returnam succes
    jsonSuccess(['message' => 'Documentul a fost sters cu succes!']);
}

// ==================================================
// jsonSuccess() - trimite un raspuns JSON de succes
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
// ==================================================
function jsonError($message) 
{
    echo json_encode([
        'success' => false,
        'error' => $message 
    ], JSON_UNESCAPED_UNICODE);
    exit;
}