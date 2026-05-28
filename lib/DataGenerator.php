<?php

// ==================================================
// lib/DataGenerator.php - Generatorul de date aleatorii
// Aceasta clasa foloseste FieldTypes pentru a genera
// seturi de date realiste, pe baza unei scheme definite
// de utilizator 
// ==================================================

class DataGenerator 
{
    // -- Proprietati private --

    // Instanta bazei de date
    private $db;

    // --------------------------------------------------
    // Constructor - initializeaza conexiunea la baza de date 
    // --------------------------------------------------
    public function __construct()
    {
        // Obtinem instanta unica a bazei de date (Singleton)
        $this->db = Database::getInstance();
    }

    // --------------------------------------------------
    // generate() - genereaza mai multe randuri de date
    // $fields - array cu campurile schemei
    //          format: [['name' => 'nume', 'type' => 'full_name'], ...]
    // $count - numarul de randuri generat
    // Returneaza array cu toate randurile generate
    // --------------------------------------------------
    public function generate(array $fields, int $count = 10): array 
    {
        // Validam numarul de randuri (intre 1 si MAX_ROWS)
        $count = max(1, min($count, MAX_ROWS));

        // Array-ul care va contine toate randurile generate
        $rows = [];

        // Generam fiecare rand
        for ($i = 0; $i < $count; $i++) {
            // Generam un rand pe baza campurilor schemei
            $rows[] = $this->generateRow($fields);
        }

        // Returnam toate randurile generate
        return $rows;
    }

    // --------------------------------------------------
    // generateRow() - genereaza un singur rand de date 
    // $fields - array cu definitiile campurilor
    // Returneaza array asociativ (nume_camp => valoare)
    // --------------------------------------------------
    private function generateRow(array $fields): array 
    {
        // Array-ul pentru un singur rand
        $row = [];

        // Parcurgem fiecare camp din schema 
        foreach ($fields as $field) {
            // Numele campului (ex: 'nume', 'email')
            $name = $field['name'] ?? 'camp';

            // Tipul campului (ex: 'full_name', 'email')
            $type = $field['type'] ?? 'text';

            // Optiunile campului (ex: min, max pentru numere)
            $options = $field['options'] ?? [];

            // Generam valoarea folosind FiledTypes
            $row[$name] = FieldTypes::generate($type, $options);
        }

        // Returnam randul generat
        return $row;
    }

    // --------------------------------------------------
    // generateFromSchema() - genereaza date dintr-o schema salvata in DB
    // $schemaId - ID-ul schemei din tabela schemas
    // $count - numarul de randuri de generat
    // --------------------------------------------------
    public function generateFromSchema(int $schemaId, int $count = 10): array 
    {
        // Cautam schema in baza de date
        $schema = $this->db->fetchOne(
            'SELECT * FROM schemas WHERE id = ?',
            [$schemaId]
        );

        // Daca schema nu exista, aruncam o exceptie
        if (!$schema) {
            throw new Exception('Schema nu a fost gasita!');
        }

        // Decodificam campurile schemei din JSON
        $fields = json_decode($schema['fields'], true);

        // Verificam daca JSON-ul a fost decodat corect
        if (!$fields) {
            throw new Exception('Schema are un format invalid!');
        }

        // Generam si returnam datele
        return $this->generate($fields, $count);
    }

    // --------------------------------------------------
    // saveSchema() - salveaza o schema de campuri in baza de date
    // $name - numele schemei (ex: 'Schema CV')
    // $fields - array cu campurile schemei
    // $userId - ID-ul utilizatorului creator
    // Returneaza ID-ul schemei salvate
    // --------------------------------------------------
    public function saveSchema(string $name, array $fields, $userId = null): int 
    {
        // Codificam campurile ca JSON pentru stocare
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE);

        // Inseram schema in baza de date
        $id = $this->db->insert('schemas', [
            'name' => $name,
            'fields' => $fieldsJson,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Inregistram actiunea in logs
        $this->db->log('save_schema', "Schema salvata: {$name}", $userId);

        // Returnam ID-ul schemei create
        return $id;
    }

    // --------------------------------------------------
    // getAllSchemas() - returneaza toate schemele din DB
    // --------------------------------------------------
    public function getAllSchemas(): array 
    {
        // Selectam toate schemele ordonate dupa data crearii
        return $this->db->fetchAll(
            'SELECT * FROM schemas ORDER BY created_at DESC'
        );
    }

    // --------------------------------------------------
    // deleteSchema() - sterge o schema din baza de date 
    // $id - ID-ul schemei de sters
    // --------------------------------------------------
    public function deleteSchema(int $id): void 
    {
        // Stergem schema din baza de date
        $this->db->delete('schemas', 'id = ?', [$id]);

        // Inregistram actiunea in logs
        $this->db->log('delete_schema', "Schema stearsa ID: {$id}");
    }

    // --------------------------------------------------
    // toCSV() - converteste datele generate in format CSV
    // $rows - array cu randurile de date
    // $delimiter - separatorul (implicit virgula)
    // Returneaza string-ul CSV complet
    // --------------------------------------------------
    public function toCSV(array $rows, string $delimiter = ','): string 
    {
        // Daca nu avem date, returnam string gol
        if (empty($rows)) {
            return '';
        }

        // Deschidem un buffer de memorie pentru scriere
        $output = fopen('php://temp', 'r+');

        // Scriem header-ul CSV (numele coloanelor)
        fputcsv($output, array_keys($rows[0]), $delimiter);

        // Scriem fiecare rand de date
        foreach ($rows as $row) {
            fputcsv($output, $row, $delimiter);
        }

        // Ne intoarcem la inceputul bufferului
        rewind($output);

        // Citim tot continutul bufferului
        $csv = stream_get_contents($output);

        // Inchidem bufferul
        fclose($output);

        // Returnam string-ul CSV
        return $csv;
    }

    // --------------------------------------------------
    // toJSON() - converteste datele generate in format JSON
    // $rows - array cu randurile de date
    // Returneaza string-ul JSON format
    // --------------------------------------------------
    public function toJSON(array $rows): string 
    {
        // Codificam datele ca JSON cu suport pentru caractere speciale
        return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}