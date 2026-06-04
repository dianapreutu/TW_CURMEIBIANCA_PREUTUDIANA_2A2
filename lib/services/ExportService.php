<?php

// lib/services/ExportService.php - Serviciu pentru exportul documentelor si datelor
// Centralizeaza logica de export in formatele: html, pdf, csv, json
// ==================================================

class ExportService
{
    private $db;
    private $templateEngine;
    private $pdfExporter;
    private $csvHandler;
    private $dataService;

    public function __construct(
        Database $db = null,
        TemplateEngine $templateEngine = null,
        PdfExporter $pdfExporter = null,
        CsvHandler $csvHandler = null,
        DataService $dataService = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->templateEngine = $templateEngine ?? new TemplateEngine();
        $this->pdfExporter = $pdfExporter ?? new PdfExporter($this->db, $this->templateEngine);
        $this->csvHandler = $csvHandler ?? new CsvHandler($this->db);
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

        return [
            'format' => 'html',
            'download_url' => BASE_URL . '/generated/html/' . basename($document['html_path']),
            'filename' => pathinfo($document['html_path'], PATHINFO_FILENAME) . '.html',
            'message' => 'Document HTML pregatit pentru descarcare.'
        ];
    }

    private function exportAsPdf(array $document): array
    {
        $pdfPath = GENERATED_PDF_PATH . '/' . ($document['pdf_path'] ?? '');
        if (!empty($document['pdf_path']) && file_exists($pdfPath)) {
            return [
                'format' => 'pdf',
                'download_url' => BASE_URL . '/generated/pdf/' . basename($document['pdf_path']),
                'filename' => basename($document['pdf_path']),
                'message' => 'PDF existent returnat.'
            ];
        }

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

        $rawRowData = $this->extractDataFromHtml(file_get_contents($htmlPath), $fieldKeys);
        $rowData = [];
        foreach ($fields as $field) {
            $rowData[$field['label']] = $rawRowData[$field['field']] ?? '';
        }

        $csvFilename = 'export_' . $document['id'] . '_' . date('Ymd_His') . '.csv';
        $csvPath = UPLOADS_PATH . '/' . $csvFilename;
        $csvString = $this->csvHandler->exportToString($headers, [$rowData]);

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
        $csvString = $this->csvHandler->exportToString($headers, $rows);
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
                return json_decode($schema['fields_json'], true) ?? [];
            }
        }

        if (!empty($document['template_id'])) {
            $template = $this->db->fetchOne('SELECT fields_json FROM templates WHERE id = ?', [$document['template_id']]);
            if ($template) {
                return json_decode($template['fields_json'], true) ?? [];
            }
        }

        return [];
    }

    private function extractDataFromHtml(string $html, array $fieldKeys): array
    {
        $data = [];
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