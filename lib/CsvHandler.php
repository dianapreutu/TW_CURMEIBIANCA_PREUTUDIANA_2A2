<?php
//lib/CsvHandler.php - clasa pt import/export CSV (gestioneaza importul fisiereleor CSV incarcate de utilizatori)
//exportul datelor generate in format CSV, salvarea fisierlor CSV importate in BD

class CsvHandler {
    //instanta bazei de date primita prin constructor

    private $db;

    //separator implicit pt CSV

    private $delimiter;

    //encodingul implicit pt fisierele CSV 
    private $encoding;

    // Constructor
    // $db - instanta Database (Singleton)
    // $delimiter - separatorul coloanelor (implicit virgula)
    // $encoding - encodingul fisierului (implicit UTF-8)

    public function __construct($db, $delimiter = ',', $encoding = 'UTF-8') {
    $this->db = $db;
    $this->delimiter = $delimiter;
    $this->encoding = $encoding;
}

    // IMPORT CSV

    //importFromFile() - importa un fisier CSV incarcat
    //$filepath - calea catre fisierul CSV pe disk
    // $userId    - id-ul utilizatorului care importa
    //$originalName - numele original al fisierului incarcat

     // Returneaza array cu headers, rows, row_count, import_id

     public function importFromFile(string $filePath, int $userId, string $originalName): array
    {
        // Verificam daca fisierul exista
        if (!file_exists($filePath)) {
            throw new Exception('Fisierul CSV nu a fost gasit: ' . $filePath);
        }
 
        // Parsam fisierul CSV
        $parsed = $this->parseFile($filePath);
 
// Salvam informatiile despre import in baza de date
        $importId = $this->db->insert('csv_imports', [
            'user_id'       => $userId,
            'original_name' => $originalName,
            'file_path'     => $filePath,
            'row_count'     => $parsed['row_count'],
            'headers_json'  => json_encode($parsed['headers'], JSON_UNESCAPED_UNICODE)
        ]);
 
        // Logam actiunea
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

    // parseFile() - citeste si parseaza un fisier CSV
    // $filePath - calea catre fisierul CSV
    // Returneaza array cu headers si rows

    public function parseFile(string $filePath): array
    {
        $headers = [];
        $rows    = [];
 
        // Deschidem fisierul pentru citire
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new Exception('Nu s-a putut deschide fisierul CSV.');
        }
 
        // Prima linie = headerele coloanelor
        $rawHeaders = fgetcsv($handle, 0, $this->delimiter);
        if ($rawHeaders === false) {
            fclose($handle);
            throw new Exception('Fisierul CSV este gol sau invalid.');
        }
 
        // Curatam headerele (trim + conversie encoding daca e necesar)
        $headers = array_map(function($h) {
            return $this->sanitizeString(trim($h));
        }, $rawHeaders);
 
        // Citim randurile de date
        while (($row = fgetcsv($handle, 0, $this->delimiter)) !== false) {
            // Sarim randurile goale
            if (empty(array_filter($row))) continue;
 
            // Construim un array asociativ header => valoare
            $rowData = [];
            foreach ($headers as $index => $header) {
                $value = $row[$index] ?? '';
                // curatam valoarea pentru a preveni XSS
                $rowData[$header] = $this->sanitizeString($value);
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
 
    // parseString() - parseaza un string CSV direct
    // Util pentru CSV primit ca string (nu fisier)
    // $csvString - continutul CSV ca string
    // Returneaza array cu headers si rows

    public function parseString(string $csvString): array
    {
        // Scriem string-ul intr-un fisier temporar si il parsam
        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tmpFile, $csvString);
        $result = $this->parseFile($tmpFile);
        unlink($tmpFile); // stergem fisierul temporar
        return $result;
    }
 

    // handleUpload() - gestioneaza upload-ul unui fisier CSV
    // Muta fisierul din tmp in directorul de uploads
    // $fileArray - $_FILES['csv_file'] de la formularul HTML
    // $userId    - id-ul utilizatorului
    // Returneaza rezultatul importului

    public function handleUpload(array $fileArray, int $userId): array
    {
        // Verificam daca a aparut o eroare la upload
        if ($fileArray['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Eroare la incarcarea fisierului: cod ' . $fileArray['error']);
        }
 
        // Verificam extensia fisierului (securitate)
        $originalName = basename($fileArray['name']);
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            throw new Exception('Doar fisierele CSV sunt acceptate.');
        }
 
        // Verificam dimensiunea fisierului (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if ($fileArray['size'] > $maxSize) {
            throw new Exception('Fisierul este prea mare. Dimensiunea maxima este 5MB.');
        }
 
        // Generam un nume unic pentru fisier (evitam suprascrierile)
        $uniqueName = time() . '_' . uniqid() . '.csv';
        $destPath   = UPLOADS_PATH . '/' . $uniqueName;
 
        // Mutam fisierul din directorul tmp in uploads/
        if (!move_uploaded_file($fileArray['tmp_name'], $destPath)) {
            throw new Exception('Nu s-a putut salva fisierul incarcat.');
        }
 
        // Importam si returnam rezultatul
        return $this->importFromFile($destPath, $userId, $originalName);
    }
 
    // EXPORT CSV

    // exportToString() - exporta date ca string CSV
    // $headers - array cu numele coloanelor
    // $rows    - array de array-uri asociative cu datele
    // Returneaza string-ul CSV generat

    public function exportToString(array $headers, array $rows): string
    {
        // Deschidem un buffer de memorie in loc de fisier
        $output = fopen('php://temp', 'r+');
 
        // Adaugam BOM pentru compatibilitate cu Excel (UTF-8)
        // Fara BOM, Excel poate afisa gresit diacriticele
        fwrite($output, "\xEF\xBB\xBF");
 
        // Scriem linia de headere
        fputcsv($output, $headers, $this->delimiter);
 
        // Scriem fiecare rand de date
        foreach ($rows as $row) {
            // Extragem valorile in ordinea headerelor
            $rowValues = array_map(function($header) use ($row) {
                return $row[$header] ?? '';
            }, $headers);
            fputcsv($output, $rowValues, $this->delimiter);
        }
 
        // Citim continutul bufferului
        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);
 
        return $csvString;
    }
 
    // exportToFile() - exporta date intr-un fisier CSV
    // $headers  - array cu numele coloanelor
    // $rows     - array de array-uri asociative cu datele
    // $filename - numele fisierului de output (fara extensie)
    // Returneaza calea catre fisierul generat

    public function exportToFile(array $headers, array $rows, string $filename = 'export'): string
    {
        // Generam numele fisierului cu timestamp
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        $filePath = UPLOADS_PATH . '/' . $safeName . '_' . date('Ymd_His') . '.csv';
 
        // Generam continutul CSV
        $csvString = $this->exportToString($headers, $rows);
 
        // Scriem in fisier
        if (file_put_contents($filePath, $csvString) === false) {
            throw new Exception('Nu s-a putut crea fisierul CSV de export.');
        }
 
        return $filePath;
    }

    // downloadCsv() - trimite fisierul CSV catre browser( declanseaza descarcarea directa in browser)
    // $headers  - array cu numele coloanelor
    // $rows     - array de array-uri cu datele
    // $filename - numele fisierului descarcat

    public function downloadCsv(array $headers, array $rows, string $filename = 'export'): void
    {
        // Generam continutul CSV
        $csvString = $this->exportToString($headers, $rows);
 
        // Setam headerele HTTP pentru descarcare
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '.csv"');
        header('Content-Length: ' . strlen($csvString));
        header('Pragma: no-cache');
        header('Expires: 0');
 
        // Trimitem continutul
        echo $csvString;
        exit;
    }

    // UTILITARE
 
    // getImportHistory() - returneaza istoricul importurilor
    // unui utilizator din baza de date
    // $userId - id-ul utilizatorului

    public function getImportHistory(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM csv_imports WHERE user_id = ? ORDER BY uploaded_at DESC',
            [$userId]
        );
    }
    
    // getImportById() - returneaza un import dupa id

    public function getImportById(int $importId, int $userId): ?array
    {
        $result = $this->db->fetchOne(
            'SELECT * FROM csv_imports WHERE id = ? AND user_id = ?',
            [$importId, $userId]
        );
        return $result ?: null;
    }

    // sanitizeString() - curata un string de caractere periculoase(previne XSS)

    private function sanitizeString(string $value): string
    {
        // Eliminam spatiile de la inceput si sfarsit
        $value = trim($value);
 
        // Convertim caracterele speciale HTML in entitati
        // Previne XSS daca valoarea e afisata direct in HTML
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
 
        return $value;
    }
 
    // detectDelimiter() - detecteaza automat separatorul CSV
    // Incearca sa determine daca e virgula, punct-virgula sau tab
    // $filePath - calea catre fisierul CSV
    public function detectDelimiter(string $filePath): string
    {
        // Citim prima linie din fisier
        $handle = fopen($filePath, 'r');
        if ($handle === false) return ',';
 
        $firstLine = fgets($handle);
        fclose($handle);
 
        // Numaram aparitiile fiecarui separator posibil
        $delimiters = [
            ','  => substr_count($firstLine, ','),
            ';'  => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
            '|'  => substr_count($firstLine, '|'),
        ];
 
        // Returnam separatorul cu cele mai multe aparitii
        arsort($delimiters);
        return array_key_first($delimiters);
    }
}