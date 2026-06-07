<?php

// lib/Database.php
// Clasa pentru gestionarea conexiunii la baza de date SQLite
// Implementeaza pattern-ul Singleton - o singura instanta in toata aplicatia
// Toate operatiunile SQL trec prin aceasta clasa

class Database
{
    private static $instance = null;
    private $pdo = null;

    // Constructorul este privat - nu se poate instantia direct din exterior
    private function __construct()
    {
        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);

            // Configuram PDO: exceptii la erori, rezultate ca array-uri asociative
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // SQLite nu activeaza cheile externe implicit
            $this->pdo->exec('PRAGMA foreign_keys = ON;');

            $this->initializeSchema();

        } catch (PDOException $e) {
            die('Eroare la conectarea la baza de date: ' . $e->getMessage());
        }
    }

    // Returneaza instanta unica a clasei, creand-o daca nu exista
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // Citeste si executa schema.sql pentru a crea tabelele la prima rulare
    private function initializeSchema()
    {
        $schemaFile = ROOT_PATH . '/db/schema.sql';

        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $this->pdo->exec($sql);
        }
    }

    // Executa o interogare SQL cu prepared statements pentru a preveni SQL Injection
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception('Eroare SQL: ' . $e->getMessage());
        }
    }

    // Returneaza toate randurile unui rezultat SQL
    public function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll();
    }

    // Returneaza un singur rand din rezultat
    public function fetchOne($sql, $params = [])
    {
        return $this->query($sql, $params)->fetch();
    }

    // Insereaza un rand intr-un tabel si returneaza ID-ul generat
    public function insert($table, $data)
    {
        $columns      = array_keys($data);
        $columnList   = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));

        return $this->pdo->lastInsertId();
    }

    // Actualizeaza randuri intr-un tabel pe baza unei conditii WHERE
    public function update($table, $data, $condition, $params = [])
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = ?";
        }
        $setList = implode(', ', $setParts);

        $sql       = "UPDATE {$table} SET {$setList} WHERE {$condition}";
        $allParams = array_merge(array_values($data), $params);

        $this->query($sql, $allParams);
    }

    // Sterge randuri dintr-un tabel pe baza unei conditii WHERE
    public function delete($table, $condition, $params = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$condition}";
        $this->query($sql, $params);
    }

    // Inregistreaza o actiune in tabela de loguri pentru audit
    public function log($action, $details = '', $userId = null)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $this->insert('logs', [
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $details,
            'ip_address'  => $ip
        ]);
    }

    // Impiedicam clonarea si deserializarea pentru a respecta Singleton
    private function __clone() {}
    public function __wakeup() {}
}