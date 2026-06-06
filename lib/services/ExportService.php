<?php

// lib/services/ExportService.php - Serviciu pentru exportul documentelor si datelor
// Centralizeaza logica de export in formatele: html, pdf, csv, json
// ==================================================

class ExportService
{
    private $db;
    private $templateEngine;
    private $pdfExporter;
    private $csvService;
    private $dataService;

    public function __construct(
        Database $db = null,
        TemplateEngine $templateEngine = null,
        PdfExporter $pdfExporter = null,
        CsvService $csvService = null,
        DataService $dataService = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->templateEngine = $templateEngine ?? new TemplateEngine();
        $this->pdfExporter = $pdfExporter ?? new PdfExporter($this->db, $this->templateEngine);
        $this->csvService = $csvService ?? new CsvService($this->db);
        $this->dataService = $dataService ?? new DataService();
    }

    public function exportDocument(int $documentId, int $userId, string $format): array
    {
        $document = $this->loadDocument($documentId, $userId);
        if (!$document) {
            throw new Exception('Documentul nu a fost gasit.');
        }

        if (!in_array($format, ['html', 'pdf', 'csv', 'json'], true)) {
            throw new Exception('Format invalid. Formatele acceptate sunt: html, pdf, csv, json.');
        }

        $result = [];
        switch ($format) {
            case 'html':
                $result = $this->exportAsHtml($document);
                break;
            case 'pdf':
                $result = $this->exportAsPdf($document);
                break;
            case 'csv':
                $result = $this->exportAsCsv($document);
                break;
            case 'json':
                $result = $this->exportAsJson($document);
                break;
        }

        $this->logExport($document['id'], $userId, $format, $result['filename']);
        return $result;
    }

    public function exportData(array $fields, int $rows, string $format, int $userId): array
    {
        if (empty($fields) || !is_array($fields)) {
            throw new Exception('Nu au fost specificate campuri pentru export.');
        }

        $format = strtolower(trim($format));
        if (!in_array($format, ['csv', 'json'], true)) {
            throw new Exception('Format invalid pentru export date. Acceptat: csv, json.');
        }

        $generatedRows = $this->dataService->generateRows($fields, max(1, min($rows, MAX_ROWS)));
        $headers = array_column($fields, 'label');
        $exportRows = [];
        foreach ($generatedRows as $row) {
            $exportRow = [];
            foreach ($fields as $field) {
                $exportRow[$field['label']] = $row[$field['field']] ?? '';
            }
            $exportRows[] = $exportRow;
        }

        if ($format === 'csv') {
            return $this->exportGeneratedCsv($headers, $exportRows, $userId);
        }

        return $this->exportGeneratedJson($headers, $fields, $exportRows, $userId);
    }

    private function loadDocument(int $documentId, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT d.*, t.label as template_label
             FROM documents d
             LEFT JOIN templates t ON d.template_id = t.id
             WHERE d.id = ? AND d.user_id = ?',
            [$documentId, $userId]
        );
    }

  private function exportAsHtml(array $document): array
{
    $htmlPath = GENERATED_HTML_PATH . '/' . ($document['html_path'] ?? '');
    if (empty($document['html_path']) || !file_exists($htmlPath)) {
        throw new Exception('Fisierul HTML al documentului nu exista.');
    }

    $html = file_get_contents($htmlPath);

    // Aplicam stilizare daca fisierul nu e deja stilizat
    if (strpos($html, '<!DOCTYPE html>') === false) {
        $templateId = (int)($document['template_id'] ?? 0);
        $cerereIds = [2];
        $facturaIds = [3];
        if (in_array($templateId, $cerereIds)) {
            $docTitle = 'CERERE';
            $color = '#1a3a5c';
        } elseif (in_array($templateId, $facturaIds)) {
            $docTitle = 'FACTURĂ';
            $color = '#1a5c2a';
        } else {
            $docTitle = 'CURRICULUM VITAE';
            $color = '#3a1a5c';
        }

        $styledHtml = '<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>' . $docTitle . '</title>
<style>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap");
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: "Inter", Arial, sans-serif; font-size: 11pt; color: #333; background: linear-gradient(135deg, #f0f4ff 0%, #fafafa 100%); min-height: 100vh; padding: 40px 20px; }
    .doc-wrapper { max-width: 820px; margin: 0 auto; }
    .doc-header { background: linear-gradient(135deg, ' . $color . ' 0%, ' . $color . 'cc 100%); color: white; border-radius: 12px 12px 0 0; padding: 32px 40px; position: relative; overflow: hidden; }
    .doc-header::before { content: ""; position: absolute; top: -40px; right: -40px; width: 160px; height: 160px; background: rgba(255,255,255,0.08); border-radius: 50%; }
    .doc-header::after { content: ""; position: absolute; bottom: -20px; left: 20px; width: 80px; height: 80px; background: rgba(255,255,255,0.05); border-radius: 50%; }
    .doc-header h1 { font-size: 26pt; font-weight: 700; letter-spacing: 3px; margin-bottom: 6px; position: relative; }
    .doc-header .subtitle { font-size: 9pt; opacity: 0.75; position: relative; }
    .doc-body { background: white; padding: 36px 40px; box-shadow: 0 8px 32px rgba(0,0,0,0.10); }
    p { margin: 12px 0; line-height: 1.8; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; }
    p:last-child { border-bottom: none; }
    strong { color: ' . $color . '; min-width: 180px; display: inline-block; font-weight: 600; vertical-align: top; }
p { margin: 12px 0; line-height: 1.8; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; display: flex; gap: 8px; }
    table { width: 100%; border-collapse: collapse; margin: 16px 0; }
    th { background: ' . $color . '; color: white; padding: 10px 12px; text-align: left; font-weight: 600; }
    td { padding: 9px 12px; border-bottom: 1px solid #eee; }
    tr:nth-child(even) td { background: #fafafa; }
    h2 { color: ' . $color . '; font-size: 12pt; font-weight: 700; margin: 20px 0 10px; padding-left: 10px; border-left: 3px solid ' . $color . '; }
    .doc-footer { background: #f8f8f8; border-radius: 0 0 12px 12px; padding: 14px 40px; font-size: 8.5pt; color: #aaa; display: flex; justify-content: space-between; border-top: 1px solid #eee; }
    .print-btn { display: block; text-align: center; margin: 20px auto 0; padding: 10px 28px; background: ' . $color . '; color: white; border: none; border-radius: 6px; font-size: 10pt; cursor: pointer; font-family: inherit; letter-spacing: 1px; }
    .print-btn:hover { opacity: 0.88; }
    @media print { .print-btn { display: none; } body { background: white; padding: 0; } .doc-header { border-radius: 0; } .doc-footer { border-radius: 0; } }
</style>
</head>
<body>
<div class="doc-header">
    <h1>' . $docTitle . '</h1>
    <div class="subtitle">Generat la ' . date('d.m.Y H:i') . '</div>
</div>
<div class="doc-body">
' . $html . '
</div>
<div class="doc-footer">Document generat automat &bull; ' . date('d.m.Y H:i') . '</div>
</body>
</html>';

        $exportFilename = pathinfo($document['html_path'], PATHINFO_FILENAME) . '_export.html';
$exportPath = GENERATED_HTML_PATH . '/' . $exportFilename;
file_put_contents($exportPath, $styledHtml);
    }

  return [
    'format' => 'html',
    'download_url' => BASE_URL . '/generated/html/' . $exportFilename,
    'filename' => $exportFilename,
    'message' => 'Document HTML pregatit pentru descarcare.'
];
}

    private function exportAsPdf(array $document): array
    {
       $pdfPath = GENERATED_PDF_PATH . '/' . ($document['pdf_path'] ?? '');

        $exportedPath = $this->pdfExporter->exportFromDocument($document['id'], (int)$document['user_id']);
        return [
            'format' => 'pdf',
            'download_url' => BASE_URL . '/generated/pdf/' . basename($exportedPath),
            'filename' => basename($exportedPath),
            'message' => 'PDF generat cu succes.'
        ];
    }

    private function exportAsCsv(array $document): array
    {
        $htmlPath = GENERATED_HTML_PATH . '/' . ($document['html_path'] ?? '');
        if (empty($document['html_path']) || !file_exists($htmlPath)) {
            throw new Exception('Fisierul documentului nu exista.');
        }

        $fields = $this->getDocumentFields($document);
        if (empty($fields)) {
            throw new Exception('Nu s-au putut obtine campurile documentului.');
        }

        $headers = array_column($fields, 'label');
        $fieldKeys = array_column($fields, 'field');

        $rawRowData = $this->extractDataFromHtml(file_get_contents($htmlPath), $fields);
        $rowData = [];
        foreach ($fields as $field) {
            $rowData[$field['label']] = $rawRowData[$field['field']] ?? '';
        }

        $csvFilename = 'export_' . $document['id'] . '_' . date('Ymd_His') . '.csv';
        $csvPath = UPLOADS_PATH . '/' . $csvFilename;
        $csvString = $this->csvService->exportToString($headers, [$rowData]);

        file_put_contents($csvPath, $csvString);

        return [
            'format' => 'csv',
            'download_url' => BASE_URL . '/uploads/' . basename($csvFilename),
            'filename' => basename($csvFilename),
            'message' => 'CSV generat cu succes.'
        ];
    }

    private function exportAsJson(array $document): array
    {
        $fields = $this->getDocumentFields($document);

        $exportData = [
            'document_id' => $document['id'],
            'title' => $document['title'] ?? '',
            'template' => $document['template_label'] ?? 'Schema personalizata',
            'generated_at' => $document['created_at'] ?? '',
            'exported_at' => date('Y-m-d H:i:s'),
            'fields' => $fields
        ];

        $jsonFilename = 'export_' . $document['id'] . '_' . date('Ymd_His') . '.json';
        $jsonPath = UPLOADS_PATH . '/' . $jsonFilename;
        file_put_contents($jsonPath, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'format' => 'json',
            'download_url' => BASE_URL . '/uploads/' . basename($jsonFilename),
            'filename' => basename($jsonFilename),
            'message' => 'JSON generat cu succes.'
        ];
    }

    private function exportGeneratedCsv(array $headers, array $rows, int $userId): array
    {
        $filename = 'data_export_' . date('Ymd_His') . '.csv';
        $path = UPLOADS_PATH . '/' . $filename;
        $csvString = $this->csvService->exportToString($headers, $rows);
        file_put_contents($path, $csvString);
        $this->db->log('export', 'Export date CSV: ' . count($rows) . ' randuri', $userId);

        return [
            'format' => 'csv',
            'download_url' => BASE_URL . '/uploads/' . basename($filename),
            'filename' => basename($filename),
            'message' => 'Date exportate ca CSV.'
        ];
    }

    private function exportGeneratedJson(array $headers, array $fields, array $rows, int $userId): array
    {
        $filename = 'data_export_' . date('Ymd_His') . '.json';
        $path = UPLOADS_PATH . '/' . $filename;
        file_put_contents($path, json_encode([
            'exported_at' => date('Y-m-d H:i:s'),
            'rows_count' => count($rows),
            'fields' => $fields,
            'data' => $rows
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->db->log('export', 'Export date JSON: ' . count($rows) . ' randuri', $userId);

        return [
            'format' => 'json',
            'download_url' => BASE_URL . '/uploads/' . basename($filename),
            'filename' => basename($filename),
            'message' => 'Date exportate ca JSON.'
        ];
    }
private function getDocumentFields(array $document): array
{
    if (!empty($document['schema_id'])) {
        $schema = $this->db->fetchOne('SELECT fields_json FROM schemas WHERE id = ?', [$document['schema_id']]);
        if ($schema) {
            $fields = json_decode($schema['fields_json'], true) ?? [];
            if (!empty($fields)) return $fields;
        }
    }

    if (!empty($document['template_id'])) {
        $template = $this->db->fetchOne('SELECT fields_json FROM templates WHERE id = ?', [$document['template_id']]);
        if ($template) {
            $decoded = json_decode($template['fields_json'], true);
            if (is_array($decoded)) return $decoded;
            
            // E HTML - extragem variabilele
            $typeMap = [
                'nume' => 'full_name', 'email' => 'email', 'telefon' => 'phone',
                'adresa' => 'address', 'data_nasterii' => 'date', 'cnp' => 'cnp',
                'ocupatie' => 'job_title', 'studii' => 'education',
                'firma' => 'company', 'nr_factura' => 'invoice_number',
                'cui' => 'cui', 'iban' => 'iban', 'pret' => 'price',
                'tva' => 'tva', 'data' => 'date', 'oras' => 'city',
                'judet' => 'county', 'produs' => 'product', 'suma' => 'price',
                'cantitate' => 'number', 'pret_unitar' => 'price',
                'nume_solicitant' => 'full_name', 'detalii' => 'paragraph',
                'subiect' => 'text', 'data_emitere' => 'date',
                'furnizor' => 'company', 'cui_furnizor' => 'cui',
                'client' => 'company', 'cui_client' => 'cui'
            ];
            preg_match_all('/\{\{(\w+)\}\}/', $template['fields_json'], $matches);
            $fields = [];
            foreach ($matches[1] as $var) {
                if (strtoupper($var) === $var) continue;
                $fields[] = [
                    'field' => $var,
                    'type' => $typeMap[$var] ?? 'text',
                    'label' => $var
                ];
            }
            if (!empty($fields)) return $fields;
        }
    }

    return [];
}
    

    private function extractDataFromHtml(string $html, array $fields): array
{
    $data = [];

    foreach ($fields as $field) {
        $key = $field['field'] ?? '';
        $label = $field['label'] ?? $key;

        if (!$key) {
            continue;
        }

        // Caz 1: HTML cu data-field="nume"
        $patternDataField = '/data-field="' . preg_quote($key, '/') . '"[^>]*>([^<]*)</i';
        if (preg_match($patternDataField, $html, $matches)) {
            $data[$key] = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            continue;
        }

        // Caz 2: HTML generat ca <p><strong>Label:</strong> valoare</p>
        $patternLabel = '/<strong>\s*' . preg_quote($label, '/') . '\s*:?\s*<\/strong>\s*(.*?)\s*<\/p>/is';
        if (preg_match($patternLabel, $html, $matches)) {
            $value = strip_tags($matches[1]);
            $data[$key] = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            continue;
        }

        $data[$key] = '';
    }

    return $data;
}

    private function logExport(int $documentId, int $userId, string $format, string $filePath): void
    {
        $this->db->insert('exports', [
            'document_id' => $documentId,
            'user_id' => $userId,
            'format' => $format,
            'file_path' => $filePath
        ]);

        $this->db->log('export', 'Export ' . strtoupper($format) . ': document ID ' . $documentId, $userId);
    }
}