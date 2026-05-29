<?php

// api/export.php - API pentru exportul documentelor
// Gestioneaza exportul documentelor in formatele: HTML, PDF, CSV, JSON
// Metode HTTP suportate: POST
// Depinde de: lib/Database.php, lib/PdfExporter.php, lib/CsvHandler.php, lib/TemplateEngine.php

require_once __DIR__ . '/../config.php';

// Setam headerele pentru raspuns JSON
header('Content-Type: application/json; charset=UTF-8');

// Obtinem instanta bazei de date
$db = Database::getInstance();

// Verificam autentificarea utilizatorului
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Trebuie sa fii autentificat pentru a exporta documente.'
    ]);
    exit;
}

// Obtinem metoda HTTP
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Doar metoda POST este acceptata.'
    ]);
    exit;
}

// Citim datele din request
$data   = getRequestData();
$action = $data['action'] ?? '';

// Initializam clasele necesare
$templateEngine = new TemplateEngine();
$pdfExporter    = new PdfExporter($db, $templateEngine);
$csvHandler     = new CsvHandler($db);

// Rutam catre functia corespunzatoare
try {
    switch ($action) {

        // Exporta un document existent din DB
        case 'export_document':
            exportDocument($data, $userId, $db, $pdfExporter, $csvHandler);
            break;

        // Exporta date generate direct (fara document salvat)
        case 'export_data':
            exportData($data, $userId, $csvHandler);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Actiune necunoscuta: ' . htmlspecialchars($action)
            ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Eroare server: ' . $e->getMessage()
    ]);
}

// FUNCTII DE EXPORT

// exportDocument() - exporta un document existent din DB
// Suporta formatele: html, pdf, csv, json

function exportDocument(array $data, int $userId, $db, $pdfExporter, $csvHandler): void
{
    $documentId = (int)($data['document_id'] ?? 0);
    $format     = strtolower(trim($data['format'] ?? ''));

    // Validam parametrii
    if ($documentId <= 0) {
        jsonError('ID document invalid.');
        return;
    }

    $validFormats = ['html', 'pdf', 'csv', 'json'];
    if (!in_array($format, $validFormats)) {
        jsonError('Format invalid. Formatele acceptate sunt: html, pdf, csv, json.');
        return;
    }

    // Incarcam documentul din DB
    // Verificam ca documentul apartine utilizatorului curent
    $document = $db->fetchOne(
        'SELECT d.*, t.label as template_label
         FROM documents d
         LEFT JOIN templates t ON d.template_id = t.id
         WHERE d.id = ? AND d.user_id = ?',
        [$documentId, $userId]
    );

    if (!$document) {
        http_response_code(404);
        jsonError('Documentul nu a fost gasit.');
        return;
    }

    // Exportam in formatul cerut
    switch ($format) {

        case 'html':
            exportAsHtml($document, $userId, $db);
            break;

        case 'pdf':
            exportAsPdf($document, $userId, $db, $pdfExporter);
            break;

        case 'csv':
            exportAsCsv($document, $userId, $db, $csvHandler);
            break;

        case 'json':
            exportAsJson($document, $userId, $db);
            break;
    }
}

// exportAsHtml() - exporta documentul ca fisier HTML

function exportAsHtml(array $document, int $userId, $db): void
{
    // Verificam ca fisierul HTML exista
    $htmlPath = GENERATED_HTML_PATH . '/' . $document['html_path'];

    if (!$document['html_path'] || !file_exists($htmlPath)) {
        jsonError('Fisierul HTML al documentului nu exista.');
        return;
    }

    // Generam URL-ul de descarcare
    $downloadUrl = BASE_URL . '/generated/html/' . $document['html_path'];

    // Inregistram exportul in DB
    logExport($document['id'], $userId, 'html', $document['html_path'], $db);

    echo json_encode([
        'success'      => true,
        'format'       => 'html',
        'download_url' => $downloadUrl,
        'filename'     => pathinfo($document['html_path'], PATHINFO_FILENAME) . '.html',
        'message'      => 'Document HTML pregatit pentru descarcare.'
    ]);
}

// exportAsPdf() - exporta documentul ca fisier PDF

function exportAsPdf(array $document, int $userId, $db, $pdfExporter): void
{
    // Daca PDF-ul exista deja, il returnam direct
    if ($document['pdf_path'] && file_exists(GENERATED_PDF_PATH . '/' . $document['pdf_path'])) {
        $downloadUrl = BASE_URL . '/generated/pdf/' . $document['pdf_path'];

        echo json_encode([
            'success'      => true,
            'format'       => 'pdf',
            'download_url' => $downloadUrl,
            'filename'     => $document['pdf_path'],
            'message'      => 'PDF existent returnat.'
        ]);
        return;
    }

    // Generam PDF-ul din documentul HTML
    $pdfPath     = $pdfExporter->exportFromDocument($document['id'], $userId);
    $pdfFilename = basename($pdfPath);
    $downloadUrl = BASE_URL . '/generated/pdf/' . $pdfFilename;

    // Inregistram exportul
    logExport($document['id'], $userId, 'pdf', $pdfFilename, $db);

    echo json_encode([
        'success'      => true,
        'format'       => 'pdf',
        'download_url' => $downloadUrl,
        'filename'     => $pdfFilename,
        'message'      => 'PDF generat cu succes.'
    ]);
}

// exportAsCsv() - exporta datele documentului ca CSV

function exportAsCsv(array $document, int $userId, $db, $csvHandler): void
{
    // Citim fisierul HTML si extragem datele
    $htmlPath = GENERATED_HTML_PATH . '/' . $document['html_path'];

    if (!$document['html_path'] || !file_exists($htmlPath)) {
        jsonError('Fisierul documentului nu exista.');
        return;
    }

    // Obtinem campurile din schema sau sablon
    $fields = getDocumentFields($document, $db);

    if (empty($fields)) {
        jsonError('Nu s-au putut obtine campurile documentului.');
        return;
    }

    // Generam numele fisierului CSV
    $csvFilename = 'export_' . $document['id'] . '_' . date('Ymd_His') . '.csv';
    $csvPath     = UPLOADS_PATH . '/' . $csvFilename;

    // Construim headerele si un rand de date din document
    $headers  = array_column($fields, 'label');
    $fieldKeys = array_column($fields, 'field');

    // Citim datele din HTML (extragem valorile)
    $rowData = extractDataFromHtml(file_get_contents($htmlPath), $fieldKeys);

    // Exportam ca CSV
    $csvString = $csvHandler->exportToString($headers, [$rowData]);
    file_put_contents($csvPath, $csvString);

    $downloadUrl = BASE_URL . '/uploads/' . $csvFilename;

    // Inregistram exportul
    logExport($document['id'], $userId, 'csv', $csvFilename, $db);

    echo json_encode([
        'success'      => true,
        'format'       => 'csv',
        'download_url' => $downloadUrl,
        'filename'     => $csvFilename,
        'message'      => 'CSV generat cu succes.'
    ]);
}

// exportAsJson() - exporta datele documentului ca JSON

function exportAsJson(array $document, int $userId, $db): void
{
    // Obtinem campurile documentului
    $fields = getDocumentFields($document, $db);

    // Construim obiectul JSON de export
    $exportData = [
        'document_id'    => $document['id'],
        'title'          => $document['title'],
        'template'       => $document['template_label'] ?? 'Schema personalizata',
        'generated_at'   => $document['created_at'],
        'exported_at'    => date('Y-m-d H:i:s'),
        'fields'         => $fields
    ];

    // Salvam fisierul JSON
    $jsonFilename = 'export_' . $document['id'] . '_' . date('Ymd_His') . '.json';
    $jsonPath     = UPLOADS_PATH . '/' . $jsonFilename;

    file_put_contents(
        $jsonPath,
        json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    $downloadUrl = BASE_URL . '/uploads/' . $jsonFilename;

    // Inregistram exportul
    logExport($document['id'], $userId, 'json', $jsonFilename, $db);

    echo json_encode([
        'success'      => true,
        'format'       => 'json',
        'download_url' => $downloadUrl,
        'filename'     => $jsonFilename,
        'message'      => 'JSON generat cu succes.'
    ]);
}

// exportData() - exporta date generate direct (fara document salvat)
// Folosit din generator.js pentru export rapid

function exportData(array $data, int $userId, $csvHandler): void
{
    $format = strtolower(trim($data['format'] ?? ''));
    $fields = $data['fields'] ?? [];
    $rows   = (int)($data['rows'] ?? DEFAULT_ROWS);

    if (empty($fields)) {
        jsonError('Nu au fost specificate campuri pentru export.');
        return;
    }

    // Generam datele cu DataGenerator
    $generator   = new DataGenerator();
    $generatedRows = $generator->generateRows($fields, $rows);

    $headers   = array_column($fields, 'label');
    $fieldKeys = array_column($fields, 'field');

    // Construim randurile cu labeluri ca headere
    $exportRows = [];
    foreach ($generatedRows as $row) {
        $exportRow = [];
        foreach ($fields as $field) {
            $exportRow[$field['label']] = $row[$field['field']] ?? '';
        }
        $exportRows[] = $exportRow;
    }

    if ($format === 'csv') {
        // Salvam CSV
        $filename    = 'data_export_' . date('Ymd_His') . '.csv';
        $filePath    = UPLOADS_PATH . '/' . $filename;
        $csvString   = $csvHandler->exportToString($headers, $exportRows);
        file_put_contents($filePath, $csvString);

        // Logam
        $db = Database::getInstance();
        $db->log('export', 'Export date CSV: ' . $rows . ' randuri', $userId);

        echo json_encode([
            'success'      => true,
            'format'       => 'csv',
            'download_url' => BASE_URL . '/uploads/' . $filename,
            'filename'     => $filename,
            'message'      => 'Date exportate ca CSV.'
        ]);

    } elseif ($format === 'json') {
        // Salvam JSON
        $filename  = 'data_export_' . date('Ymd_His') . '.json';
        $filePath  = UPLOADS_PATH . '/' . $filename;

        file_put_contents(
            $filePath,
            json_encode([
                'exported_at' => date('Y-m-d H:i:s'),
                'rows_count'  => count($exportRows),
                'fields'      => $fields,
                'data'        => $exportRows
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // Logam
        $db = Database::getInstance();
        $db->log('export', 'Export date JSON: ' . $rows . ' randuri', $userId);

        echo json_encode([
            'success'      => true,
            'format'       => 'json',
            'download_url' => BASE_URL . '/uploads/' . $filename,
            'filename'     => $filename,
            'message'      => 'Date exportate ca JSON.'
        ]);

    } else {
        jsonError('Format invalid pentru export date. Acceptat: csv, json.');
    }
}

// UTILITARE

// getDocumentFields() - obtine campurile unui document din schema sau sablon

function getDocumentFields(array $document, $db): array
{
    // Incercam sa obtinem campurile din schema personalizata
    if ($document['schema_id']) {
        $schema = $db->fetchOne(
            'SELECT fields_json FROM schemas WHERE id = ?',
            [$document['schema_id']]
        );
        if ($schema) {
            return json_decode($schema['fields_json'], true) ?? [];
        }
    }

    // Altfel obtinem campurile din sablon
    if ($document['template_id']) {
        $template = $db->fetchOne(
            'SELECT fields_json FROM templates WHERE id = ?',
            [$document['template_id']]
        );
        if ($template) {
            return json_decode($template['fields_json'], true) ?? [];
        }
    }

    return [];
}

// extractDataFromHtml() - extrage valorile din HTML
// Folosit pentru exportul CSV al unui document generat

function extractDataFromHtml(string $html, array $fieldKeys): array
{
    $data = [];
    // Incercam sa extragem valorile din atributele data-field
    // Ex: <span data-field="nume">Ion Popescu</span>
    foreach ($fieldKeys as $key) {
        $pattern = '/data-field="' . preg_quote($key, '/') . '"[^>]*>([^<]*)</';
        if (preg_match($pattern, $html, $matches)) {
            $data[$key] = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        } else {
            $data[$key] = '';
        }
    }
    return $data;
}

// logExport() - inregistreaza exportul in tabela exports

function logExport(int $documentId, int $userId, string $format, string $filePath, $db): void
{
    $db->insert('exports', [
        'document_id' => $documentId,
        'user_id'     => $userId,
        'format'      => $format,
        'file_path'   => $filePath
    ]);

    $db->log('export', 'Export ' . strtoupper($format) . ': document ID ' . $documentId, $userId);
}


// getRequestData() - citeste datele din body-ul cererii

function getRequestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $body = file_get_contents('php://input');
        return json_decode($body, true) ?? [];
    }
    return $_POST;
}

// jsonError() - trimite un raspuns de eroare JSON

function jsonError(string $message): void
{
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
}
