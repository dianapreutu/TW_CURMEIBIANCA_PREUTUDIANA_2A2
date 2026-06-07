<?php

// lib/services/DataService.php
// Serviciu pentru generarea datelor si importul CSV
// Centralizeaza validarea, generarea si importul datelor

class DataService
{
    private $generator;
    private $csvService;

    public function __construct(DataGenerator $generator = null, CsvService $csvService = null)
    {
        $this->generator  = $generator  ?? new DataGenerator();
        $this->csvService = $csvService ?? new CsvService(Database::getInstance());
    }

    // Genereaza un numar de randuri pe baza schemei de campuri primite
    public function generateRows(array $fields, int $count): array
    {
        $this->validateFields($fields);
        $count = max(1, min($count, MAX_ROWS));

        return $this->generator->generate($fields, $count);
    }

    // Genereaza un singur rand de date si il returneaza ca array asociativ
    public function generateRecord(array $fields): array
    {
        $rows = $this->generateRows($fields, 1);
        return $rows[0] ?? [];
    }

    // Importa un fisier CSV incarcat de utilizator via CsvService
    public function importCsv(array $file, int $userId): array
    {
        return $this->csvService->handleUpload($file, $userId);
    }

    // Valideaza si returneaza primul rand dintr-un fisier CSV incarcat
    public function parseCsvRow(array $file): array
    {
        if (empty($file) || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Nu a fost incarcat niciun fisier CSV valid!');
        }

        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            throw new Exception('Fisierul trebuie sa fie de tip CSV!');
        }

        $result = $this->csvService->handleUpload($file, 0);
        if (empty($result['rows']) || !is_array($result['rows'])) {
            throw new Exception('CSV-ul nu contine randuri valide.');
        }

        return $result['rows'][0];
    }

    // Verifica ca schema contine cel putin un camp si ca tipurile sunt valide
    private function validateFields(array $fields): void
    {
        if (empty($fields)) {
            throw new Exception('Schema trebuie sa contina cel putin un camp!');
        }

        $validTypes = array_keys(FieldTypes::getAll());
        foreach ($fields as $field) {
            if (!isset($field['type']) || !in_array($field['type'], $validTypes, true)) {
                throw new Exception('Tip de camp invalid: ' . ($field['type'] ?? 'necunoscut'));
            }
        }
    }
}