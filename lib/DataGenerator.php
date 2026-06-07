<?php

// lib/DataGenerator.php
// Generatorul de date aleatorii pe baza unei scheme de campuri
// Foloseste FieldTypes pentru a produce valori realiste

class DataGenerator
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // Genereaza mai multe randuri de date pe baza schemei de campuri
    public function generate(array $fields, int $count = 10): array
    {
        $count = max(1, min($count, MAX_ROWS));
        $rows  = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = $this->generateRow($fields);
        }

        return $rows;
    }

    // Genereaza un singur rand si coreleaza email-ul cu numele daca ambele exista
    private function generateRow(array $fields): array
    {
        $row = [];

        foreach ($fields as $field) {
            $name       = $field['field']   ?? 'camp';
            $type       = $field['type']    ?? 'text';
            $options    = $field['options'] ?? [];
            $row[$name] = FieldTypes::generate($type, $options);
        }

        // Construim email-ul pe baza numelui generat
        $nameValue = $row['nume'] ?? $row['full_name'] ?? null;
        $emailKey  = isset($row['email']) ? 'email' : null;

        if ($nameValue && $emailKey) {
            $normalized = strtolower($nameValue);
            $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $normalized);
            $normalized = preg_replace('/[^a-z\s]/', '', $normalized);
            $parts      = explode(' ', trim($normalized));

            $domains = ['gmail.com', 'yahoo.com', 'yahoo.ro', 'hotmail.com'];
            $domain  = $domains[array_rand($domains)];
            $suffix  = rand(0, 99) > 50 ? rand(10, 99) : '';

            $row[$emailKey] = implode('.', $parts) . $suffix . '@' . $domain;
        }

        return $row;
    }

    // Genereaza date pe baza unei scheme salvate in baza de date
    public function generateFromSchema(int $schemaId, int $count = 10): array
    {
        $schema = $this->db->fetchOne(
            'SELECT * FROM schemas WHERE id = ?',
            [$schemaId]
        );

        if (!$schema) {
            throw new Exception('Schema nu a fost gasita!');
        }

        $fields = json_decode($schema['fields_json'], true);
        if (!$fields) {
            throw new Exception('Schema are un format invalid!');
        }

        return $this->generate($fields, $count);
    }

    // Salveaza o schema de campuri in baza de date si returneaza ID-ul generat
    public function saveSchema(string $name, array $fields, $userId = null): int
    {
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE);

        $id = $this->db->insert('schemas', [
            'name'        => $name,
            'fields_json' => $fieldsJson,
            'user_id'     => $userId ?? 1,
            'rows_count'  => 10
        ]);

        $this->db->log('save_schema', "Schema salvata: {$name}", $userId);

        return $id;
    }

    // Returneaza toate schemele din baza de date, ordonate dupa data crearii
    public function getAllSchemas(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM schemas ORDER BY created_at DESC'
        );
    }

    // Sterge o schema din baza de date dupa ID
    public function deleteSchema(int $id): void
    {
        $this->db->delete('schemas', 'id = ?', [$id]);
        $this->db->log('delete_schema', "Schema stearsa ID: {$id}");
    }

    // Converteste un array de randuri in format CSV si returneaza string-ul generat
    public function toCSV(array $rows, string $delimiter = ','): string
    {
        if (empty($rows)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($rows[0]), $delimiter);

        foreach ($rows as $row) {
            fputcsv($output, $row, $delimiter);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    // Converteste un array de randuri in format JSON
    public function toJSON(array $rows): string
    {
        return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}