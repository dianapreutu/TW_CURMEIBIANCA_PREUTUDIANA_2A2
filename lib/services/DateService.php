<?php

// lib/services/DataService.php - Serviciu pentru generarea de date si import CSV
// Centralizeaza logica de validare, generare si gestionare a schemelor
// ==================================================

class DataService
{
    private $generator;
    private $csvHandler;

    public function __construct(DataGenerator $generator = null, CsvHandler $csvHandler = null)
    {
        $this->generator = $generator ?? new DataGenerator();
        $this->csvHandler = $csvHandler ?? new CsvHandler(Database::getInstance());
    }

    public function generateRows(array $fields, int $count): array
    {
        $this->validateFields($fields);
        $count = max(1, min($count, MAX_ROWS));

        return $this->generator->generate($fields, $count);
    }

    public function generateRecord(array $fields): array
    {
        $rows = $this->generateRows($fields, 1);
        return $rows[0] ?? [];
    }

    public function importCsv(array $file, int $userId): array
    {
        return $this->csvHandler->handleUpload($file, $userId);
    }

    public function parseCsvRow(array $file): array
    {
        if (empty($file) || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Nu a fost incarcat niciun fisier CSV valid!');
        }

        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            throw new Exception('Fisierul trebuie sa fie de tip CSV!');
        }

        $result = $this->csvHandler->handleUpload($file, 0);
        if (empty($result['rows']) || !is_array($result['rows'])) {
            throw new Exception('CSV-ul nu contine randuri valide.');
        }

        return $result['rows'][0];
    }

    public function getFieldTypes(): array
    {
        return FieldTypes::getAll();
    }

    public function saveSchema(string $name, array $fields, int $userId): int
    {
        $this->validateFields($fields);

        if (empty(trim($name))) {
            throw new Exception('Numele schemei este obligatoriu!');
        }

        return $this->generator->saveSchema($name, $fields, $userId);
    }

    public function listSchemas(int $userId): array
    {
        $schemas = $this->generator->getAllSchemas();

        foreach ($schemas as $key => $schema) {
            if ((int)($schema['user_id'] ?? 0) !== $userId) {
                unset($schemas[$key]);
                continue;
            }

            $schemas[$key]['fields'] = json_decode($schema['fields_json'], true) ?? [];
        }

        return array_values($schemas);
    }

    public function deleteSchema(int $id, int $userId): void
    {
        if ($id <= 0) {
            throw new Exception('ID invalid!');
        }

        $schema = $this->generator->getAllSchemas();
        $found = false;
        foreach ($schema as $row) {
            if ((int)$row['id'] === $id && (int)($row['user_id'] ?? 0) === $userId) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new Exception('Schema nu a fost gasita sau nu aveti dreptul sa o stergeti.');
        }

        $this->generator->deleteSchema($id);
    }

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
