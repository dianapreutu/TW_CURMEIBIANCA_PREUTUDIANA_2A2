<?php

// lib/services/SchemaService.php - Serviciu pentru gestionarea schemelor de campuri
// Centralizeaza operatiile CRUD pentru scheme si tipurile de campuri
// ==================================================

class SchemaService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getFieldTypes(): array
    {
        return FieldTypes::getAll();
    }

    public function getSchemas(int $userId): array
    {
        $schemas = $this->db->fetchAll(
            'SELECT id, name, fields_json, rows_count, created_at, updated_at
             FROM schemas
             WHERE user_id = ?
             ORDER BY updated_at DESC',
            [$userId]
        );

        foreach ($schemas as &$schema) {
            $schema['fields'] = json_decode($schema['fields_json'], true) ?? [];
            unset($schema['fields_json']);
        }

        return $schemas;
    }

    public function getSchema(int $id, int $userId): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $schema = $this->db->fetchOne(
            'SELECT id, name, fields_json, rows_count, created_at, updated_at
             FROM schemas
             WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$schema) {
            return null;
        }

        $schema['fields'] = json_decode($schema['fields_json'], true) ?? [];
        unset($schema['fields_json']);

        return $schema;
    }

    public function saveSchema(string $name, array $fields, int $userId): int
    {
        $name = $this->sanitize($name);
        $this->validateSchema($name, $fields);

        return $this->db->insert('schemas', [
            'user_id' => $userId,
            'name' => $name,
            'fields_json' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'rows_count' => max(1, min(10, MAX_ROWS))
        ]);
    }

    public function updateSchema(int $id, array $data, int $userId): void
    {
        if ($id <= 0) {
            throw new Exception('ID schema invalid.');
        }

        $existing = $this->db->fetchOne(
            'SELECT id FROM schemas WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$existing) {
            throw new Exception('Schema nu a fost gasita.');
        }

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        if (isset($data['name']) && !empty(trim($data['name']))) {
            $updateData['name'] = $this->sanitize(trim($data['name']));
        }

        if (isset($data['fields_json'])) {
            $fields = json_decode($data['fields_json'], true);
            if (is_array($fields) && !empty($fields)) {
                $this->validateSchema($updateData['name'] ?? '', $fields);
                $updateData['fields_json'] = json_encode($fields, JSON_UNESCAPED_UNICODE);
            }
        }

        if (isset($data['rows_count'])) {
            $updateData['rows_count'] = max(1, min((int)$data['rows_count'], MAX_ROWS));
        }

        $this->db->update('schemas', $updateData, 'id = ? AND user_id = ?', [$id, $userId]);
    }

    public function deleteSchema(int $id, int $userId): void
    {
        if ($id <= 0) {
            throw new Exception('ID schema invalid.');
        }

        $existing = $this->db->fetchOne(
            'SELECT id FROM schemas WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$existing) {
            throw new Exception('Schema nu a fost gasita.');
        }

        $this->db->delete('schemas', 'id = ? AND user_id = ?', [$id, $userId]);
    }

    private function validateSchema(string $name, array $fields): void
    {
        if (empty($name)) {
            throw new Exception('Numele schemei este obligatoriu.');
        }

        if (empty($fields) || !is_array($fields)) {
            throw new Exception('Schema trebuie sa contina cel putin un camp.');
        }

        $validTypes = array_keys(FieldTypes::getAll());
        foreach ($fields as $field) {
            if (!isset($field['type']) || !in_array($field['type'], $validTypes)) {
                throw new Exception('Tip de camp invalid: ' . ($field['type'] ?? 'necunoscut'));
            }
        }
    }

    private function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
