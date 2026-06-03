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

    public function importCsv(array $file, int $userId): array
    {
        return $this->csvHandler->handleUpload($file, $userId);
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
