<?php
namespace Kai\MhbBackend20\Database;

use PDO;
use PDOException;

class DB {
    private static ?DB $instance = null;
    private PDO $connection;

    // Der Konstruktor ist private, damit niemand "new DB()" aufrufen kann
    private function __construct() {
        try {
            $dsn = "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8mb4";
            $this->connection = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Im Backend-Kontext ist eine Exception besser als ein die(), 
            // damit der Service sie ggf. loggen kann.
            throw new \Exception("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
        }
    }

    // Liefert die Instanz der DB-Klasse (das Singleton)
    public static function getInstance(): DB {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Liefert die eigentliche PDO-Verbindung
    public function getConnection(): PDO {
        return $this->connection;
    }

    // Verhindert das Klonen der Instanz
    private function __clone() {}
}