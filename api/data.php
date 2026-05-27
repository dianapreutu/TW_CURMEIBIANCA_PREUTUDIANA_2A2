<?php

// ==================================================
// api/data.php - API pentru generarea si importul datelor
// Acest fisier primeste cereri AJAX si returneaza JSON
// Operatii: generare aleatorie, import CSV, listare scheme
// ==================================================

// Includem configurarile globale
require_once '../config.php';

// Setam header-ul pentru raspuns JSON
header('Content-Type: application/json; charset=utf-8');

// Initializam clasele necesare
$db = Database::getInstance();
$generator = new DataGenerator();
$csv = new CsvHandler($db);

// Citim actiunea ceruta din parametrii GET sau POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Rutam cererea catre functia corespunzatoare
switch ($action) {
    case 'generate':        handleGenerate($generator);     break;
    case 'import_csv':      handleImportCsv($csv);      break;
    case 'get_types':       handleGetTypes();       break;
    case 'save_schema':     handleSaveSchema($generator);       break;
    case 'list_schemas':        handleListSchemas($generator);     break;
    case 'delete_schema':       handleDeleteSchema($generator);     break;
    default:        jsonError('Actiune invalida!');     break;
}

// ==================================================
// handleGenerate() - genereaza date aleatorii
// Cerere: POST api/data.php?action=generate
// Body: fields (JSON), count
// ==================================================
function handleGenerate(DataGenerator $generator)
{
    // Verificam ca cererea este de tip POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    // Citim campurile schemei din POST (trimisa ca JSON)
    $fieldsJson = $_POST['fields'] ?? '[]';
    $fields = json_decode($fieldsJson, true);

    // Verificam ca avem campurile valide
    if (empty($fields) || !is_array($fields)) {
        jsonError('Schema de campuri este invalida sau goala!');
        return;
    }

    // Citim numarul de randuri generat
    $count = intval($_POST['count'] ?? DEFAULT_ROWS);

    // Validam numarul de randuri
    if ($count <= 0 || $count > MAX_ROWS) {
        jsonError('Numarul de randuri trebuie sa fie intre 1 si ' . MAX_ROWS . '!');
        return;
    }

    // Generam datele aleatorii
    $rows = $generator->generate($fields, $count);

    // Extragem headerele (numele campurilor) din primul rand
    $headers = array_keys($rows[0]);

    // Returnam datele generate 
    jsonSuccess([
        'headers' => $headers,
        'rows' => $rows,
        'row_count' => count($rows)
    ]);
}

// ==================================================
// handleImportCSV() - importa date dintr-un fisier CSV 
// Cerere: POST api/data.php?action=import_csv
// Body: fisier CSV (multipart/form-data)
// ==================================================
function handleImportCsv(CsvHandler $csv) 
{
    // Verificam ca cererea este de tip POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    // Verificam daca a fost incarcat un fisier
    if (!isset($_FILES['csv_file'])) {
        jsonError('Nu a fost incarcat niciun fisier!');
        return;
    }

    // Incercam sa procesam fisierul CSV incarcat
    try {
        // Folosim CsvHandler pentru a gestiona upload-ul
        // userId = null deoarece nu avem autentificare implementata inca
        $result = $csv->handleUpload($_FILES['csv_file'], 0);

        // Returnam datele importate
        jsonSuccess([
            'headers' => $result['headers'],
            'rows' => $result['rows'],
            'row_count' => $result['row_count'],
            'message' => 'CSV importat cu succes! ' . $result['row_count'] . ' randuri gasite.'
        ]);

    } catch (Exception $e) {
        // Returnam eroarea daca importul a esuat
        jsonError('Eroare la importul CSV: ' . $e->getMessage());
    }
}

// ==================================================
// handleGetTypes() - returneaza toate tipurile de campuri disponibile
// Cerere: GET api/data.php?action=get_types
// Folosit de interfata pentru a popula lista de tipuri
// ==================================================
function handleGetTypes() 
{
    // Obtinem toate tipurile disponibile din FieldTypes
    $types = FieldTypes::getAll();

    // Construim array-ul de raspuns cu tip, nume si descriere
    $result = [];
    foreach ($types as $key => $label) {
        $result[] = [
            'type' => $key,
            'label' => $label,
            'description' => FieldTypes::describe($key)
        ];
    }

    // Returnam lista de tipuri
    jsonSuccess($result);
}

// ==================================================
// handleSaveSchema() - salveaza o schema de campuri
// Cerere: POST api/data.php?action=save_schema
// Body: name, fields (JSON)
// ==================================================
function handleSaveSchema(DataGenerator $generator)
{
    // Verificam ca cererea este de tip POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Metoda HTTP invalida!');
        return;
    }

    // Citim si curatam numele schemei
    $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));

    // Validam numele schemei
    if (empty($name)) {
        jsonError('Numele schemei este obligatoriu!');
        return;
    }

    // Citim campurile schemei din POST
    $fieldsJson = $_POST['fields'] ?? '[]';
    $fields = json_decode($fieldsJson, true);

    // Validam campurile 
    if (empty($fields) || !is_array($fields)) {
        jsonError('Schema trebuie sa contina cel putin un camp!');
        return;
    }

    // Salvam schema in baza de date
    $id = $generator->saveSchema($name, $fields);

    // Returnam succes
    jsonSuccess([
        'id' => $id,
        'message' => 'Schema a fost salvata cu succes!'
    ]);
}

// ==================================================
// handleListSchemas() - returneaza toate schemele salvate
// Cerere: GET api/data.php?action=list_schemas
// ==================================================
function handleListSchemas(DataGenerator $generator) 
{
    // Obtinem toate schemele din baza de date
    $schemas = $generator->getAllSchemas();

    // Decodificam campurile JSON pentru fiecare schema 
    foreach ($schemas as &$schema) {
        $schema['fields'] = json_decode($schema['fields'], true);
    }

    // Returnam lista de scheme
    jsonSuccess($schemas);
}

// ==================================================
// handleDeleteSchema() - sterge o schema salvata
// Cerere: POST api/data.php?action=delete_schema
// Body: id
// ==================================================
function handleDeleteSchema(DataGenerator $generator) 
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

    // Stergem schema din baza de date
    $generator->deleteSchema($id);

    // Returnam succes
    jsonSuccess(['message' => 'Schema a fost stearsa cu succes!']);
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