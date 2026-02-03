<?php
// backend/classes/Database.php

// Import des classes PDO
use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        // Charger la configuration depuis config.php
        $config = require __DIR__ . '/../config/config.php';
        $dbConfig = $config['db'];

        try {
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
            $this->connection = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Erreur de connexion à la base de données: " . $e->getMessage());
            throw new Exception("Impossible de se connecter à la base de données");
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            try {
                self::$instance = new self();
            } catch (Exception $e) {
                error_log("Erreur création instance Database: " . $e->getMessage());
                throw new Exception("Impossible d'initialiser la base de données: " . $e->getMessage());
            }
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}