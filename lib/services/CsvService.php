<?php

// lib/services/CsvService.php
// Serviciu pentru import si export CSV
// Gestioneaza incarcarea fisierelor CSV, parsarea, exportul si istoricul importurilor

class CsvService
{
    private $db;
    private $delimiter;
    private $encoding;

    public function __construct($db, $delimiter = ',', $encoding = 'UTF-8')
    {
        $this->db        = $db;
        $this->delimiter = $delimiter;
        $this->encoding  = $encoding;
    }

    // Importa un fisier CSV de pe disk si salveaza metadatele in baza de date
    public function importFromFile(string $filePath, int $userId, string $originalName): array
    {
        if (!file_exists($filePath)) {
            throw new Exception('Fisierul CSV nu a fost gasit: ' . $filePath);
        }

        $parsed = $this->parseFile($filePath);

        $importId = $this->db->insert('csv_imports', [
            'user_id'       => $userId,
            'original_name' => $originalName,
            'file_path'     => $filePath,
            'row_count'     => $parsed['row_count'],
            'headers_json'  => json_encode($parsed['headers'], JSON_UNESCAPED_UNICODE)
        ]);

        $this->db->log(
            'import',
            'Import CSV: ' . $originalName . ' (' . $parsed['row_count'] . ' randuri)',
            $userId
        );

        return [
            'import_id' => $importId,
            'headers'   => $parsed['headers'],
            'rows'      => $parsed['rows'],
            'row_count' => $parsed['row_count']
        ];
    }

    // Citeste si parseaza un fisier CSV, returneaza headers si rows
    public function parseFile(string $filePath): array
    {
        $headers = [];
        $rows    = [];

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new Exception('Nu s-a putut deschide fisierul CSV.');
        }

        // Prima linie contine headerele coloanelor
        $rawHeaders = fgetcsv($handle, 0, $this->delimiter);
        if ($rawHeaders === false) {
            fclose($handle);
            throw new Exception('Fisierul CSV este gol sau invalid.');
        }

        $headers = array_map(function($h) {
            return $this->sanitizeString(trim($h));
        }, $rawHeaders);

        // Citim randurile si construim array-uri asociative header => valoare
        while (($row = fgetcsv($handle, 0, $this->delimiter)) !== false) {
            if (empty(array_filter($row))) continue;

            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = $this->sanitizeString($row[$index] ?? '');
            }
            $rows[] = $rowData;
        }

        fclose($handle);

        return [
            'headers'   => $headers,
            'rows'      => $rows,
            'row_count' => count($rows)
        ];
    }

    // Parseaza un string CSV direct, folosind un fisier temporar
    public function parseString(string $csvString): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tmpFile, $csvString);
        $result = $this->parseFile($tmpFile);
        unlink($tmpFile);
        return $result;
    }

    // Gestioneaza upload-ul unui fisier CSV, valideaza si muta fisierul in uploads/
    public function handleUpload(array $fileArray, int $userId): array
    {
        if ($fileArray['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Eroare la incarcarea fisierului: cod ' . $fileArray['error']);
        }

        $originalName = basename($fileArray['name']);
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'csv') {
            throw new Exception('Doar fisierele CSV sunt acceptate.');
        }

        // Verificam dimensiunea fisierului (max 5MB)
        $maxSize = 5 * 1024 * 1024;
        if ($fileArray['size'] > $maxSize) {
            throw new Exception('Fisierul este prea mare. Dimensiunea maxima este 5MB.');
        }

        $uniqueName = time() . '_' . uniqid() . '.csv';
        $destPath   = UPLOADS_PATH . '/' . $uniqueName;

        if (!move_uploaded_file($fileArray['tmp_name'], $destPath)) {
            throw new Exception('Nu s-a putut salva fisierul incarcat.');
        }

        return $this->importFromFile($destPath, $userId, $originalName);
    }

    // Genereaza si returneaza un string CSV din headers si rows
    // Include BOM pentru compatibilitate cu Excel
    public function exportToString(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'r+');

        // BOM necesar pentru afisarea corecta a diacriticelor in Excel
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers, $this->delimiter);

        foreach ($rows as $row) {
            $rowValues = array_map(function($header) use ($row) {
                return $row[$header] ?? '';
            }, $headers);
            fputcsv($output, $rowValues, $this->delimiter);
        }

        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);

        return $csvString;
    }

    // Exporta datele intr-un fisier CSV pe disk si returneaza calea generata
    public function exportToFile(array $headers, array $rows, string $filename = 'export'): string
    {
        $safeName  = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        $filePath  = UPLOADS_PATH . '/' . $safeName . '_' . date('Ymd_His') . '.csv';
        $csvString = $this->exportToString($headers, $rows);

        if (file_put_contents($filePath, $csvString) === false) {
            throw new Exception('Nu s-a putut crea fisierul CSV de export.');
        }

        return $filePath;
    }

    // Trimite fisierul CSV catre browser si declanseaza descarcarea directa
    public function downloadCsv(array $headers, array $rows, string $filename = 'export'): void
    {
        $csvString = $this->exportToString($headers, $rows);
        $safeName  = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '.csv"');
        header('Content-Length: ' . strlen($csvString));
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csvString;
        exit;
    }

    // Returneaza istoricul importurilor unui utilizator din baza de date
    public function getImportHistory(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM csv_imports WHERE user_id = ? ORDER BY uploaded_at DESC',
            [$userId]
        );
    }

    // Returneaza un import specific dupa ID si utilizator
    public function getImportById(int $importId, int $userId): ?array
    {
        $result = $this->db->fetchOne(
            'SELECT * FROM csv_imports WHERE id = ? AND user_id = ?',
            [$importId, $userId]
        );
        return $result ?: null;
    }

    // Curata un string de caractere periculoase pentru a preveni XSS
    private function sanitizeString(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Detecteaza automat separatorul CSV din prima linie a fisierului
    public function detectDelimiter(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) return ',';

        $firstLine = fgets($handle);
        fclose($handle);

        // Numaram aparitiile fiecarui separator posibil si il alegem pe cel mai frecvent
        $delimiters = [
            ','  => substr_count($firstLine, ','),
            ';'  => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
            '|'  => substr_count($firstLine, '|'),
        ];

        arsort($delimiters);
        return array_key_first($delimiters);
    }
}