<?php

// ==================================================
// lib/Database.php - Clasa pentru gestionarea bazei de date
// Aceasta clasa foloseste pattern-ul Singleton;
// -> exista o singura instanta a conexiunii la baza de date in toata aplicatia
// Toate operatiunile SQL trec prin aceasta clasa
// ==================================================

class Database
{
    // -- Proprietati private --

    // Instanta unica a clasei (pattern Singleton)
    private static $instance = null;

    // Obiectul conexiunii PDO la baza de date SQLite
    private $pdo = null;

    // --------------------------------------------------
    // Constructorul este privat in pattern-ul Singleton
    // Nu se poate face un 'new Database()' din exterior
    // --------------------------------------------------
    private function __construct()
    {
        try {
            // Cream conexiunea PDO la fisierul SQLite 
            $this->pdo = new PDO('sqlite:' . DB_PATH);

            // Setam modul de raportare a erorilor PDO
            // ERRMODE_EXCEPTION arunca exceptii la erori SQL
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Returnam rezultatele ca array-uri asociative
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Activam suportul pentru chei externe in SQLite
            // SQLite nu le activeaza implicit, trebuie facut manual
            $this->pdo->exec('PRAGMA foreign_keys = ON;');

            // Initializam schema bazei de date
            $this->initializeSchema();

        } catch (PDOException $e) {
            // Daca conexiunea esueaza, oprim aplicatia 
            die('Eroare la conectarea la baza de date: ' . $e->getMessage());
        }
    }

    // --------------------------------------------------
    // getInstance() - returneaza instanta unica a clasei
    // Daca nu exista deja o instanta, o creeaza
    // --------------------------------------------------
    public static function getInstance()
    {
        // Daca instanta nu exista inca, o cream
        if (self::$instance === null) {
            self::$instance = new self();
        }

        // Returnam instanta existenta
        return self::$instance;
    }

    // --------------------------------------------------
    // initializeSchema() - citeste si executa schema.sql
    // Creeaza tabelele daca nu exista deja in baza de date
    // --------------------------------------------------
    private function initializeSchema() 
    {
        // Calea catre fisierul cu structura bazei de date 
        $schemaFile = ROOT_PATH . '/db/schema.sql';

        // Verificam daca fisierul schema.sql exista
        if (file_exists($schemaFile)) {
            // Citim tot continutul fisierului SQL
            $sql = file_get_contents($schemaFile);

            // Executam toate comenzile SQL din fisier
            $this->pdo->exec($sql);
        }
    }

    // --------------------------------------------------
    // query() - executa o interogare SQL cu parametri
    // Folosim prepared statements pentru a preveni SQL Injection
    // $sql - interogarea SQL cu placeholder-uri (WHERE id = ?)
    // $params - array cu valorile pentru placeholder-uri
    // --------------------------------------------------
    public function query($sql, $params = [])
    {
        try {
            // Pregatim interogarea (prepared statement)
            // Aceasta separa codul SQL de date, prevenind SQL Injection
            $stmt = $this->pdo->prepare($sql);

            // Executam interogarea cu parametrii furnizati
            $stmt->execute($params);

            // Returnam statement-ul pentru a putea extrage rezultatele
            return $stmt;

        } catch (PDOException $e) {
            // In caz de eroare SQL, aruncam o exceptie
            throw new Exception('Eroare SQL: ' . $e->getMessage());
        }
    }

    // --------------------------------------------------
    // fetchAll() - returneaza toate randurile unui rezultat SQL
    // --------------------------------------------------
    public function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll();
    }

    // --------------------------------------------------
    // fetchOne()  - returneaza un singur rand din rezultat
    // Util pentru interogari care returneaza o singura inregistrare
    // --------------------------------------------------
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    // --------------------------------------------------
    // insert() - insereaza un rand intr-un tabel 
    // $table - numele tabelului
    // $data - array asociativ (coloana => valoare)
    // Returneaza ID-ul randului inserat
    // --------------------------------------------------
    public function insert($table, $data)
    {
        // Extragem numele coloanelor din array
        $columns = array_keys($data);

        // Construim lista de coloane pentru SQL: "col1, col2, col3"
        $columnList = implode(', ', $columns);

        // Construim lista de placeholder-uri: "?, ?, ?"
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        // Construim interogarea SQL completa
        $sql = "INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})";

        // Executam interogarea cu valorile din $data
        $this->query($sql, array_values($data));

        // Returnam ID-ul randului tocmai inserat
        return $this->pdo->lastInsertId();
    }

    // --------------------------------------------------
    // update() - actualizeaza randuri intr-un tabel
    // $table - numele tabelului
    // $data - array asociativ cu noile valori
    // $condition - conditia WHERE 
    // $params - parametrii pentru conditie
    // --------------------------------------------------
    public function update($table, $data, $condition, $params = [])
    {
        // Construim lista SET: "col1 = ?, col2 = ?"
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = ?";
        }
        $setList = implode(', ', $setParts);

        // Construim interogarea SQL completa
        $sql = "UPDATE {$table} SET {$setList} WHERE {$condition}";

        // Combina valorile din $data cu parametrii pentru WHERE
        $allParams = array_merge(array_values($data), $params);

        // Executam interogarea 
        $this->query($sql, $allParams);
    }

    // --------------------------------------------------
    // delete() - sterge randuri dintr-un tabel
    // $table - numele tabelului
    // $condition - conditia WHERE 
    // $params - parametrii pentru conditie
    // --------------------------------------------------
    public function delete($table, $condition, $params = [])
    {
        // Construim si executam interogarea DELETE
        $sql = "DELETE FROM {$table} WHERE {$condition}";
        $this->query($sql, $params);
    }

    // --------------------------------------------------
    // log() - inregistreaza o actiune in tabela de logs
    // Apelat din toata aplicatia pentru monitorizare
    // --------------------------------------------------
    public function log($action, $details = '', $userId = null) 
    {
        // Obtinem adresa IP a utilizatorului curent
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Inseram inregistrarea in tabela logs
        $this->insert('logs', [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $ip 
        ]);
    }

    // --------------------------------------------------
    // Impiedicam clonarea si deserializarea instantei
    // Acestea ar putea crea instante multiple (incalca Singleton)
    // --------------------------------------------------
    private function __clone() {}
    public function __wakeup() {}
}